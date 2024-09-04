<?php

namespace Crm\PaymentsModule\Repositories;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Models\Redis\RedisClientFactory;
use Crm\ApplicationModule\Models\Redis\RedisClientTrait;
use Crm\ApplicationModule\Models\Request;
use Crm\ApplicationModule\Repositories\AuditLogRepository;
use Crm\ApplicationModule\Repositories\CacheRepository;
use Crm\PaymentsModule\Events\NewPaymentEvent;
use Crm\PaymentsModule\Events\PaymentChangeStatusEvent;
use Crm\PaymentsModule\Models\OneStopShop\OneStopShop;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainerFactory;
use Crm\PaymentsModule\Models\VariableSymbolInterface;
use Crm\PaymentsModule\Models\VariableSymbolVariant;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use DateTime;
use League\Event\Emitter;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Tracy\Debugger;
use malkusch\lock\mutex\PredisMutex;

class PaymentsRepository extends Repository
{
    use RedisClientTrait;

    /** @deprecated Use \Crm\PaymentsModule\Models\Payment\PaymentStatusEnum::Form enum instead. */
    public const STATUS_FORM = 'form';
    /** @deprecated Use \Crm\PaymentsModule\Models\Payment\PaymentStatusEnum::Paid enum instead. */
    public const STATUS_PAID = 'paid';
    /** @deprecated Use \Crm\PaymentsModule\Models\Payment\PaymentStatusEnum::Fail enum instead. */
    public const STATUS_FAIL = 'fail';
    /** @deprecated Use \Crm\PaymentsModule\Models\Payment\PaymentStatusEnum::Timeout enum instead. */
    public const STATUS_TIMEOUT = 'timeout';
    /** @deprecated Use \Crm\PaymentsModule\Models\Payment\PaymentStatusEnum::Refund enum instead. */
    public const STATUS_REFUND = 'refund';
    /** @deprecated Use \Crm\PaymentsModule\Models\Payment\PaymentStatusEnum::Imported enum instead. */
    public const STATUS_IMPORTED = 'imported';
    /** @deprecated Use \Crm\PaymentsModule\Models\Payment\PaymentStatusEnum::Prepaid enum instead. */
    public const STATUS_PREPAID = 'prepaid';
    /** @deprecated Use \Crm\PaymentsModule\Models\Payment\PaymentStatusEnum::Authorized enum instead. */
    public const STATUS_AUTHORIZED = 'authorized';

    protected $tableName = 'payments';

    public function __construct(
        Explorer $database,
        AuditLogRepository $auditLogRepository,
        RedisClientFactory $redisClientFactory,
        protected VariableSymbolInterface $variableSymbol,
        protected SubscriptionsRepository $subscriptionsRepository,
        protected PaymentItemsRepository $paymentItemsRepository,
        protected Emitter $emitter,
        protected \Tomaj\Hermes\Emitter $hermesEmitter,
        protected PaymentMetaRepository $paymentMetaRepository,
        protected CacheRepository $cacheRepository,
        protected PaymentGatewaysRepository $paymentGatewaysRepository,
        private OneStopShop $oneStopShop,
        private CountriesRepository $countriesRepository,
        private PaymentItemContainerFactory $paymentItemContainerFactory,
        private UsersRepository $usersRepository,
        private AddressesRepository $addressesRepository,
        private SubscriptionTypesRepository $subscriptionTypesRepository,
    ) {
        parent::__construct($database);
        $this->auditLogRepository = $auditLogRepository;
        $this->redisClientFactory = $redisClientFactory;
    }

    final public function add(
        ActiveRow $subscriptionType = null,
        ActiveRow $paymentGateway,
        ActiveRow $user,
        PaymentItemContainer $paymentItemContainer,
        $referer = null,
        $amount = null,
        DateTime $subscriptionStartAt = null,
        DateTime $subscriptionEndAt = null,
        $note = null,
        $additionalAmount = 0,
        $additionalType = null,
        $variableSymbol = null,
        ActiveRow $address = null,
        $recurrentCharge = false,
        array $metaData = [],
        ?ActiveRow $paymentCountry = null,
        ?string $paymentCountryResolutionReason = null,
        bool $allowUnreliablePaymentItemContainer = false,
    ) {
        if ($paymentItemContainer->isUnreliable()) {
            if ($allowUnreliablePaymentItemContainer) {
                Debugger::log("Creating payment warning: " . $paymentItemContainer->getUnreliableWarning(), Debugger::WARNING);
            } else {
                throw new \RuntimeException("Unreliable payment container is not allowed. Details: " . $paymentItemContainer->getUnreliableWarning());
            }
        }

        $this->oneStopShop->adjustPaymentItemContainerVatRates($paymentItemContainer, $paymentCountry);

        $data = [
            'user_id' => $user->id,
            'payment_gateway_id' => $paymentGateway->id,
            'status' => PaymentStatusEnum::Form->value,
            'created_at' => new DateTime(),
            'modified_at' => new DateTime(),
            'variable_symbol' => $variableSymbol ?: $this->variableSymbol->getNew($paymentGateway),
            'ip' => Request::getIp(),
            'user_agent' => Request::getUserAgent(),
            'referer' => $referer,
            'subscription_start_at' => $subscriptionStartAt,
            'subscription_end_at' => $subscriptionEndAt,
            'note' => $note,
            'additional_type' => $additionalType,
            'additional_amount' => $additionalAmount == null ? 0 : $additionalAmount,
            'address_id' => $address?->id,
            'payment_country_id' => $paymentCountry?->id,
            'payment_country_resolution_reason' => $paymentCountryResolutionReason,
            'recurrent_charge' => $recurrentCharge,
        ];

        // TODO: Additional type/amount fields are only informative and should be replaced with single/recurrent flag
        // directly on payment_items and be removed from here. additional_amount should not affect total amount anymore.

        // If amount is not provided, it's calculated based on payment items in container.
        if ($amount) {
            $data['amount'] = $amount;
        } else {
            $data['amount'] = $paymentItemContainer->totalPrice();
        }

        // It's not possible to generate payment amount based on payment items as postal fees of product module were
        // not refactored yet to separate payment item. Therefore custom "$amount" is still allowed.

        if ($data['amount'] <= 0) {
            throw new \Exception('attempt to create payment with zero or negative amount: ' . $data['amount']);
        }

        if ($subscriptionType) {
            $data['subscription_type_id'] = $subscriptionType->id;
        }

        /** @var ActiveRow $payment */
        $payment = $this->insert($data);

        $this->paymentItemsRepository->add($payment, $paymentItemContainer);

        if (!empty($metaData)) {
            $this->addMeta($payment, $metaData);
        }

        $this->emitter->emit(new NewPaymentEvent($payment));
        $this->hermesEmitter->emit(new HermesMessage('new-payment', [
            'payment_id' => $payment->id
        ]), HermesMessage::PRIORITY_HIGH);
        return $payment;
    }

    final public function addMeta($payment, $data)
    {
        if (empty($data)) {
            return null;
        }
        $added = [];
        foreach ($data as $key => $value) {
            if (!$this->paymentMetaRepository->add($payment, $key, $value)) {
                return false;
            }
        }
        return $added;
    }

    final public function copyPayment(ActiveRow $payment, array $changes = [])
    {
        $paymentItemContainer = $this->paymentItemContainerFactory->createFromPayment($payment, [SubscriptionTypePaymentItem::TYPE]);

        if ($payment->subscription_type_id) {
            $subscriptionTypePaymentItemContainer = new PaymentItemContainer();
            $subscriptionTypePaymentItemContainer->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($payment->subscription_type));

            $paymentTotalPriceWithoutVAT = round($paymentItemContainer->totalPriceWithoutVAT(), 2);
            $subscriptionTypeTotalPriceWithoutVAT = round($subscriptionTypePaymentItemContainer->totalPriceWithoutVAT(), 2);

            $totalPricesEqual = round($subscriptionTypePaymentItemContainer->totalPrice(), 2) === round($payment->amount, 2);
            $totalPricesWithoutVatEqual = $paymentTotalPriceWithoutVAT === $subscriptionTypeTotalPriceWithoutVAT;
            $totalItemsCountEqual = count($paymentItemContainer->items()) === count($subscriptionTypePaymentItemContainer->items());

            // To use items from the subscription type we need to make sure that the $totalAmounts are the same.
            // Otherwise, user would be charged different amount which we don't want.
            if ($totalPricesEqual) {
                // 1) Subscription type items could have changed their VATs. If total price without VAT isn't equal,
                // payment items should be created from the subscription type, so we get current (correct) amounts
                // without VAT and not charge the user old VAT.
                //
                // 2) We can only copy original items if the amount of items equals. If it changed (e.g. one item was
                // split into two separate items), let's use the current (correct) items from the subscription type.
                if (!$totalPricesWithoutVatEqual || !$totalItemsCountEqual) {
                    $paymentItemContainer = $subscriptionTypePaymentItemContainer;
                }
            }
        }

        // Copy all other payment types except for subscription type payment items (copied in previous step)
        $paymentItemContainer = $this->paymentItemContainerFactory->addItemsFromPaymentToContainer(
            payment: $payment,
            container: $paymentItemContainer,
            excludedPaymentItemTypes: [SubscriptionTypePaymentItem::TYPE],
        );

        $paymentData = array_merge([
            'amount' => $payment->amount,
            'user_id' => $payment->user_id,
            'subscription_type_id' => $payment->subscription_type_id,
            'payment_gateway_id' => $payment->payment_gateway_id,
            'status' => PaymentStatusEnum::Form->value,
            'created_at' => new DateTime(),
            'modified_at' => new DateTime(),
            'variable_symbol' => $payment->variable_symbol,
            'ip' => Request::getIp(),
            'user_agent' => Request::getUserAgent(),
            'referer' => null,
        ], $changes);

        // Caller can signal VS change by passing ['variable_symbol' => null] within the $changes array.
        if ($paymentData['variable_symbol'] === null) {
            $paymentGateway = $this->paymentGatewaysRepository->find($paymentData['payment_gateway_id']);
            $paymentData['variable_symbol'] = $this->variableSymbol->getNew($paymentGateway);
        }

        $resolvedCountry = $this->oneStopShop->resolveCountry(
            user: $this->usersRepository->find($paymentData['user_id']),
            paymentAddress: $this->addressesRepository->find($paymentData['address_id'] ?? null),
            paymentItemContainer: $paymentItemContainer,
            ipAddress: $paymentData['ip'],
            previousPayment: $payment,
        );
        if ($resolvedCountry) {
            $paymentData['payment_country_id'] = $resolvedCountry->country->id;
            $paymentData['payment_country_resolution_reason'] = $resolvedCountry->getReasonValue();
        }

        return $this->add(
            subscriptionType: $this->subscriptionTypesRepository->find($paymentData['subscription_type_id']),
            paymentGateway: $this->paymentGatewaysRepository->find($paymentData['payment_gateway_id']),
            user: $this->usersRepository->find($paymentData['user_id']),
            paymentItemContainer: $paymentItemContainer,
            referer: $paymentData['referer'],
            amount: $paymentData['amount'],
            subscriptionStartAt: $paymentData['subscription_start_at'] ?? null,
            subscriptionEndAt: $paymentData['subscription_end_at'] ?? null,
            note: $paymentData['note'] ?? null,
            additionalAmount: $paymentData['additional_amount'] ?? null,
            additionalType: $paymentData['additional_type'] ?? null,
            variableSymbol: $paymentData['variable_symbol'],
            address: $this->addressesRepository->find($paymentData['address_id'] ?? null),
            recurrentCharge: $paymentData['recurrent_charge'] ?? false,
            paymentCountry: $this->countriesRepository->find($paymentData['payment_country_id'] ?? null),
            paymentCountryResolutionReason: $paymentData['payment_country_resolution_reason'] ?? null,
            allowUnreliablePaymentItemContainer: true,
        );
    }

    final public function getPaymentItems(ActiveRow $payment): array
    {
        $items = [];
        foreach ($payment->related('payment_items') as $paymentItem) {
            $items[] = [
                'subscription_type_item_id' => $paymentItem->subscription_type_item_id,
                'name' => $paymentItem->name,
                'amount' => $paymentItem->amount,
                'vat' => $paymentItem->vat,
                'count' => $paymentItem->count,
                'type' => $paymentItem->type,
                'meta' => $paymentItem->related('payment_item_meta')->fetchPairs('key', 'value'),
            ];
        }
        return $items;
    }

    /**
     * @param ActiveRow $payment
     * @param string $paymentItemType
     * @return array|ActiveRow[]
     */
    final public function getPaymentItemsByType(ActiveRow $payment, string $paymentItemType): array
    {
        return $this->paymentItemsRepository->getByType($payment, $paymentItemType);
    }

    final public function update(ActiveRow &$row, $data, PaymentItemContainer $paymentItemContainer = null)
    {
        $newPaymentCountry = null;
        if (isset($data['payment_country_id']) &&
            $data['payment_country_id'] !== $row->payment_country_id) {
            $newPaymentCountry = $this->countriesRepository->find($data['payment_country_id']);
        }

        if ($paymentItemContainer) {
            $this->oneStopShop->adjustPaymentItemContainerVatRates(
                $paymentItemContainer,
                $newPaymentCountry ?? $row->payment_country,
            );
            $this->paymentItemsRepository->deleteByPayment($row);
            $this->paymentItemsRepository->add($row, $paymentItemContainer);
        } elseif ($newPaymentCountry) {
            $this->oneStopShop->adjustPaymentVatRates($row, $newPaymentCountry);
        }

        $data['modified_at'] = new DateTime();
        return parent::update($row, $data);
    }

    final public function updateStatus(ActiveRow $payment, $status, $sendEmail = false, $note = null, $errorMessage = null, $salesFunnelId = null)
    {
        // Updates of payment status may come from multiple sources simultaneously,
        // therefore we avoid running this code in parallel using mutex
        $mutex = new PredisMutex([$this->redis()], 'payments_repository_update_status_' . $payment->id, 10);
        $updated = $mutex->synchronized(function () use ($payment, $status, $note, $errorMessage) {
            // refresh payment since it may be stalled (because of waiting for mutex)
            $payment = $this->find($payment->id);

            // prevention of obsolete update and event emitting
            if ($payment->status === $status) {
                return false;
            }

            $data = [
                'status' => $status,
                'modified_at' => new DateTime()
            ];
            if (in_array($status, [PaymentStatusEnum::Paid->value, PaymentStatusEnum::Prepaid->value, PaymentStatusEnum::Authorized->value], true) && !$payment->paid_at) {
                $data['paid_at'] = new DateTime();
            }
            if ($note) {
                $data['note'] = $note;
            }
            if ($errorMessage) {
                $data['error_message'] = $errorMessage;
            }

            if (in_array($payment->status, [PaymentStatusEnum::Paid->value, PaymentStatusEnum::Prepaid->value, PaymentStatusEnum::Authorized->value], true) && $data['status'] == PaymentStatusEnum::Fail->value) {
                Debugger::log("attempt to make change status of payment #[{$payment->id}] from [{$payment->status}] to [fail]", Debugger::ERROR);
                return false;
            }

            parent::update($payment, $data);

            return true;
        });

        if ($updated) {
            $payment = $this->find($payment->id);

            $this->emitter->emit(new PaymentChangeStatusEvent($payment, $sendEmail));
            $this->hermesEmitter->emit(new HermesMessage('payment-status-change', [
                'payment_id' => $payment->id,
                'sales_funnel_id' => $payment->sales_funnel_id ?? $salesFunnelId, // pass explicit sales_funnel_id if payment doesn't contain one
                'send_email' => $sendEmail,
            ]), HermesMessage::PRIORITY_HIGH);

            return $this->find($payment->id);
        }
        return false;
    }

    /**
     * @param $variableSymbol
     * @return ActiveRow
     */
    final public function findByVs($variableSymbol)
    {
        return $this->findBy('variable_symbol', $this->variableSymbolVariants($variableSymbol));
    }

    private function variableSymbolVariants($variableSymbol)
    {
        if (!is_numeric($variableSymbol)) {
            return $variableSymbol;
        }
        $variableSymbolVariant = new VariableSymbolVariant();
        return $variableSymbolVariant->variableSymbolVariants($variableSymbol);
    }

    final public function findLastByVS(string $variableSymbol)
    {
        return $this->findAllByVS($variableSymbol)->order('created_at DESC')->limit(1)->fetch();
    }

    final public function findAllByVS(string $variableSymbol)
    {
        return $this->getTable()->where(
            'variable_symbol',
            $this->variableSymbolVariants($variableSymbol)
        );
    }

    final public function addSubscriptionToPayment(ActiveRow $subscription, ActiveRow $payment)
    {
        return parent::update($payment, ['subscription_id' => $subscription->id]);
    }

    final public function subscriptionPayment(ActiveRow $subscription)
    {
        return $this->getTable()->where(['subscription_id' => $subscription->id])->select('*')->limit(1)->fetch();
    }

    /**
     * @param int $userId
     * @return Selection
     */
    final public function userPayments($userId)
    {
        return $this->getTable()->where(['payments.user_id' => $userId])->order('created_at DESC');
    }

    /**
     * @param int $userId
     * @return Selection
     */
    final public function userRefundPayments($userId)
    {
        return $this->userPayments($userId)->where('status', PaymentStatusEnum::Refund->value);
    }

    /**
     * @param string $text
     * @param null $payment_gateway
     * @param null $subscription_type
     * @param null $status
     * @param null $start
     * @param null $end
     * @param null $sales_funnel
     * @param null $donation
     * @param null $recurrentCharge
     * @param null $referer
     * @return Selection
     */
    final public function all($text = '', $payment_gateway = null, $subscription_type = null, $status = null, $start = null, $end = null, $sales_funnel = null, $donation = null, $recurrentCharge = null, $referer = null)
    {
        $where = [];
        if ($text !== null && $text !== '') {
            $where['variable_symbol IN (?)'] = $this->variableSymbolVariants($text);
        }
        if ($payment_gateway) {
            $where['payments.payment_gateway_id'] = $payment_gateway;
        }
        if ($subscription_type) {
            $where['payments.subscription_type_id'] = $subscription_type;
        }
        if ($status) {
            $where['payments.status'] = $status;
        }
        if ($sales_funnel) {
            $where['payments.sales_funnel_id'] = $sales_funnel;
        }
        if ($donation !== null) {
            if ($donation) {
                $where[] = 'additional_amount > 0';
            } else {
                $where[] = 'additional_amount = 0';
            }
        }
        if ($start) {
            $where['(paid_at IS NOT NULL AND paid_at >= ?) OR (paid_at IS NULL AND modified_at >= ?)'] = [$start, $start];
        }
        if ($end) {
            $where['(paid_at IS NOT NULL AND paid_at < ?) OR (paid_at IS NULL AND modified_at < ?)'] = [$end, $end];
        }
        if ($recurrentCharge !== null) {
            $where['recurrent_charge'] = $recurrentCharge;
        }
        if ($referer) {
            $where['referer LIKE ?'] = "%{$referer}%";
        }

        return $this->getTable()->where($where);
    }

    final public function allWithoutOrder()
    {
        return $this->getTable();
    }

    final public function totalAmountSum($allowCached = false, $forceCacheUpdate = false)
    {
        $callable = function () {
            return $this->getTable()->where(['status' => PaymentStatusEnum::Paid->value])->sum('amount') ?? 0.00;
        };

        if ($allowCached) {
            return $this->cacheRepository->loadAndUpdate(
                'payments_paid_sum',
                $callable,
                \Nette\Utils\DateTime::from(CacheRepository::REFRESH_TIME_5_MINUTES),
                $forceCacheUpdate
            );
        }

        return $callable();
    }

    final public function totalUserAmountSum($userId)
    {
        return $this->getTable()->where(['user_id' => $userId, 'status' => PaymentStatusEnum::Paid->value])->sum('amount') ?? 0.00;
    }

    final public function getStatusPairs()
    {
        $statuses = PaymentStatusEnum::getFriendlyList();

        // Omit 'Authorized' status because it's used only for payment authorization before purchase. It shouldn't be accessible.
        unset($statuses[PaymentStatusEnum::Authorized->value]);

        return $statuses;
    }

    final public function getPaymentsWithNotes()
    {
        return $this->getTable()->where(['NOT note' => null])->order('created_at DESC');
    }

    final public function totalCount($allowCached = false, $forceCacheUpdate = false): int
    {
        $callable = function () {
            return parent::totalCount();
        };
        if ($allowCached) {
            return (int) $this->cacheRepository->loadAndUpdate(
                'payments_count',
                $callable,
                \Nette\Utils\DateTime::from(CacheRepository::REFRESH_TIME_5_MINUTES),
                $forceCacheUpdate
            );
        }
        return $callable();
    }

    final public function paidSubscribers()
    {
        return $this->database->table('subscriptions')
            ->where('start_time <= ?', $this->database::literal('NOW()'))
            ->where('end_time > ?', $this->database::literal('NOW()'))
            ->where('is_paid = 1')
            ->where('user.active = 1');
    }


    /**
     * @param DateTime $from
     * @param DateTime $to
     *
     * @return \Crm\ApplicationModule\Models\Database\Selection
     */
    final public function paidBetween(DateTime $from, DateTime $to)
    {
        return $this->getTable()->where([
            'status IN (?)' => [PaymentStatusEnum::Paid->value, PaymentStatusEnum::Prepaid->value],
            'paid_at > ?' => $from,
            'paid_at < ?' => $to,
        ]);
    }

    final public function subscriptionsWithActiveUnchargedRecurrentEndingNextTwoWeeksCount($forceCacheUpdate = false)
    {
        $callable = function () {
            return $this->subscriptionsWithActiveUnchargedRecurrentEndingBetween(
                \Nette\Utils\DateTime::from('today 00:00'),
                \Nette\Utils\DateTime::from('+14 days 23:59:59')
            )->count('*');
        };

        return $this->cacheRepository->loadAndUpdate(
            'subscriptions_with_active_uncharged_recurrent_ending_next_two_weeks_count',
            $callable,
            \Nette\Utils\DateTime::from(CacheRepository::REFRESH_TIME_5_MINUTES),
            $forceCacheUpdate
        );
    }

    final public function subscriptionsWithActiveUnchargedRecurrentEndingNextMonthCount($forceCacheUpdate = false)
    {
        $callable = function () {
            return $this->subscriptionsWithActiveUnchargedRecurrentEndingBetween(
                \Nette\Utils\DateTime::from('today 00:00'),
                \Nette\Utils\DateTime::from('+31 days 23:59:59')
            )->count('*');
        };

        return $this->cacheRepository->loadAndUpdate(
            'subscriptions_with_active_uncharged_recurrent_ending_next_month_count',
            $callable,
            \Nette\Utils\DateTime::from(CacheRepository::REFRESH_TIME_5_MINUTES),
            $forceCacheUpdate
        );
    }

    /**
     * @param DateTime $startTime
     * @param DateTime $endTime
     * @return mixed|Selection
     */
    final public function subscriptionsWithActiveUnchargedRecurrentEndingBetween(DateTime $startTime, DateTime $endTime)
    {
        return $this->database->table('subscriptions')
            ->where(':payments.id IS NOT NULL')
            ->where(':payments:recurrent_payments(parent_payment_id).status IS NULL')
            ->where(':payments:recurrent_payments(parent_payment_id).retries > ?', 0)
            ->where(':payments:recurrent_payments(parent_payment_id).state = ?', 'active')
            ->where('next_subscription_id IS NULL')
            ->where('end_time >= ?', $startTime)
            ->where('end_time <= ?', $endTime);
    }


    /**
     * Cached value since computation of the value for next two weeks interval may be slow
     *
     * @param bool $forceCacheUpdate
     * @param bool $onlyPaid
     * @return int
     */
    final public function subscriptionsWithoutExtensionEndingNextTwoWeeksCount($forceCacheUpdate = false, $onlyPaid = false)
    {
        $cacheKey = 'subscriptions_without_extension_ending_next_two_weeks_count';
        if ($onlyPaid) {
            $cacheKey = 'paid_' . $cacheKey;
        }

        $callable = function () use ($onlyPaid) {
            return $this->subscriptionsWithoutExtensionEndingBetweenCount(
                \Nette\Utils\DateTime::from('today 00:00'),
                \Nette\Utils\DateTime::from('+14 days 23:59:59'),
                $onlyPaid
            );
        };
        return $this->cacheRepository->loadAndUpdate(
            $cacheKey,
            $callable,
            \Nette\Utils\DateTime::from(CacheRepository::REFRESH_TIME_5_MINUTES),
            $forceCacheUpdate
        );
    }

    /**
     * Cached value since computation of the value for next month interval may be slow
     *
     * @param bool $forceCacheUpdate
     *
     * @return int
     */
    final public function subscriptionsWithoutExtensionEndingNextMonthCount($forceCacheUpdate = false, $onlyPaid = false)
    {
        $cacheKey = 'subscriptions_without_extension_ending_next_month_count';
        if ($onlyPaid) {
            $cacheKey = 'paid_' . $cacheKey;
        }

        $callable = function () use ($onlyPaid) {
            return $this->subscriptionsWithoutExtensionEndingBetweenCount(
                \Nette\Utils\DateTime::from('today 00:00'),
                \Nette\Utils\DateTime::from('+31 days 23:59:59'),
                $onlyPaid
            );
        };
        return $this->cacheRepository->loadAndUpdate(
            $cacheKey,
            $callable,
            \Nette\Utils\DateTime::from(CacheRepository::REFRESH_TIME_5_MINUTES),
            $forceCacheUpdate
        );
    }

    final public function subscriptionsWithoutExtensionEndingBetweenCount(DateTime $startTime, DateTime $endTime, $onlyPaid = false)
    {
        $s = $startTime;
        $e = $endTime;

        $renewedSubscriptionsEndingBetweenSql = <<<SQL
SELECT subscriptions.id 
FROM subscriptions 
LEFT JOIN payments ON subscriptions.id = payments.subscription_id 
LEFT JOIN recurrent_payments ON payments.id = recurrent_payments.parent_payment_id 
WHERE payments.id IS NOT NULL AND 
recurrent_payments.status IS NULL AND
recurrent_payments.retries > 0 AND 
recurrent_payments.state = 'active' AND
next_subscription_id IS NULL AND 
end_time >= ? AND 
end_time <= ?
SQL;

        $q = <<<SQL
        
SELECT COUNT(subscriptions.id) as total FROM subscriptions 
LEFT JOIN subscription_types ON subscription_types.id = subscriptions.subscription_type_id
WHERE subscriptions.id IS NOT NULL AND end_time >= ? AND end_time <= ? AND subscriptions.id NOT IN (
  SELECT id FROM subscriptions WHERE end_time >= ? AND end_time <= ? AND next_subscription_id IS NOT NULL
  UNION 
  ($renewedSubscriptionsEndingBetweenSql)
)
SQL;

        if ($onlyPaid) {
            $q .= " AND subscription_types.price > 0 AND subscriptions.type NOT IN ('free')";
        }

        return $this->getDatabase()->fetch($q, $s, $e, $s, $e, $s, $e)->total;
    }

    /**
     * WARNING: slow for wide intervals
     * @param DateTime $startTime
     * @param DateTime $endTime
     * @return Selection
     */
    final public function subscriptionsWithoutExtensionEndingBetween(DateTime $startTime, DateTime $endTime)
    {
        $endingSubscriptions = $this->subscriptionsRepository->subscriptionsEndingBetween($startTime, $endTime)->select('subscriptions.id')->fetchAll();
        $renewedSubscriptions = $this->subscriptionsRepository->renewedSubscriptionsEndingBetween($startTime, $endTime)->select('subscriptions.id')->fetchAll();
        $activeUnchargedSubscriptions = $this->subscriptionsWithActiveUnchargedRecurrentEndingBetween($startTime, $endTime)->select('subscriptions.id')->fetchAll();

        $ids = array_diff($endingSubscriptions, $renewedSubscriptions, $activeUnchargedSubscriptions);

        return $this->database
            ->table('subscriptions')
            ->where('id IN (?)', $ids);
    }

    /**
     * @param DateTime $from
     * @return Selection
     */
    final public function unconfirmedPayments(DateTime $from)
    {
        return $this->getTable()
            ->where('payments.status = ?', PaymentStatusEnum::Form->value)
            ->where('payments.created_at >= ?', $from)
            ->order('payments.created_at DESC');
    }

    /**
     * @param string $urlKey
     * @return \Crm\ApplicationModule\Models\Database\Selection
     */
    final public function findBySalesFunnelUrlKey(string $urlKey)
    {
        return $this->getTable()
            ->where('sales_funnel.url_key', $urlKey);
    }

    /**
     * @param ActiveRow $subscription
     * @param array $includeSubscriptionTypeIds
     * @return ActiveRow[]
     */
    public function followingSubscriptions(ActiveRow $subscription, array $includeSubscriptionTypeIds = []): array
    {
        $currentSubscription = $subscription;

        $followingSubscriptions = [];
        while (true) {
            $followingPaymentSelection = $this->getTable()
                ->where([
                    'payments.user_id' => $currentSubscription->user_id,
                    'subscription.start_time' => $currentSubscription->end_time,
                ])
                ->where('subscription.start_time > ?', $currentSubscription->start_time);

            if (count($includeSubscriptionTypeIds)) {
                $includeSubscriptionTypeIds[] = $subscription->subscription_type_id;
                $followingPaymentSelection->where([
                    'subscription.subscription_type_id' => $includeSubscriptionTypeIds,
                ]);
            }

            $followingPayment = $followingPaymentSelection->fetch();
            if ($followingPayment === null) {
                break;
            }

            $followingSubscriptions[] = $followingPayment->subscription;
            $currentSubscription = $followingPayment->subscription;
        }

        return $followingSubscriptions;
    }
}
