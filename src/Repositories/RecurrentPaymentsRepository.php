<?php

namespace Crm\PaymentsModule\Repositories;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Models\Database\Selection;
use Crm\ApplicationModule\Models\NowTrait;
use Crm\ApplicationModule\Repositories\AuditLogRepository;
use Crm\ApplicationModule\Repositories\CacheRepository;
use Crm\PaymentsModule\Events\RecurrentPaymentCreatedEvent;
use Crm\PaymentsModule\Events\RecurrentPaymentRenewedEvent;
use Crm\PaymentsModule\Events\RecurrentPaymentStateChangedEvent;
use Crm\PaymentsModule\Events\RecurrentPaymentStoppedByAdminEvent;
use Crm\PaymentsModule\Events\RecurrentPaymentStoppedByUserEvent;
use Crm\PaymentsModule\Models\Gateway;
use Crm\PaymentsModule\Models\GatewayFactory;
use Crm\PaymentsModule\Models\Gateways\RecurrentPaymentInterface;
use Crm\PaymentsModule\Models\Gateways\ReusableCardPaymentInterface;
use DateTime;
use Exception;
use League\Event\Emitter;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Tracy\Debugger;

class RecurrentPaymentsRepository extends Repository
{
    use NowTrait;

    protected $tableName = 'recurrent_payments';

    protected $auditLogExcluded = [
        'updated_at',
    ];

    /** @deprecated Use \Crm\PaymentsModule\Models\RecurrentPayment\StateEnum::UserStop enum instead. */
    const STATE_USER_STOP = 'user_stop';
    /** @deprecated Use \Crm\PaymentsModule\Models\RecurrentPayment\StateEnum::AdminStop enum instead. */
    const STATE_ADMIN_STOP = 'admin_stop';
    /** @deprecated Use \Crm\PaymentsModule\Models\RecurrentPayment\StateEnum::Active enum instead. */
    const STATE_ACTIVE = 'active';
    /** @deprecated Use \Crm\PaymentsModule\Models\RecurrentPayment\StateEnum::Pending enum instead. */
    const STATE_PENDING = 'pending';
    /** @deprecated Use \Crm\PaymentsModule\Models\RecurrentPayment\StateEnum::Charged enum instead. */
    const STATE_CHARGED = 'charged';
    /** @deprecated Use \Crm\PaymentsModule\Models\RecurrentPayment\StateEnum::ChargeFailed enum instead. */
    const STATE_CHARGE_FAILED = 'charge_failed';
    /** @deprecated Use \Crm\PaymentsModule\Models\RecurrentPayment\StateEnum::SystemStop enum instead. */
    const STATE_SYSTEM_STOP = 'system_stop';

    public function __construct(
        Explorer $database,
        AuditLogRepository $auditLogRepository,
        private readonly PaymentGatewayMetaRepository $paymentGatewayMetaRepository,
        private readonly Emitter $emitter,
        private readonly ApplicationConfig $applicationConfig,
        private readonly \Tomaj\Hermes\Emitter $hermesEmitter,
        private readonly GatewayFactory $gatewayFactory,
        private readonly CacheRepository $cacheRepository,
        private readonly PaymentMethodsRepository $paymentMethodsRepository,
    ) {
        parent::__construct($database);
        $this->auditLogRepository = $auditLogRepository;
    }

    final public function add(ActiveRow $paymentMethod, $payment, $chargeAt, $customAmount, $retries, string $note = null)
    {
        return $this->insert([
            'cid' => $paymentMethod->external_token,
            'created_at' => $this->getNow(),
            'updated_at' => $this->getNow(),
            'charge_at' => $chargeAt,
            'payment_method_id' => $paymentMethod->id,
            'payment_gateway_id' => $payment->payment_gateway->id,
            'subscription_type_id' => $payment->subscription_type_id,
            'custom_amount' => $customAmount,
            'retries' => $retries,
            'user_id' => $payment->user->id,
            'parent_payment_id' => $payment->id,
            'state' => self::STATE_ACTIVE,
            'note' => $note,
        ]);
    }

    /**
     * @deprecated
     */
    final public function addV1($cid, $payment, $chargeAt, $customAmount, $retries, string $note = null)
    {
        $paymentMethod = $this->paymentMethodsRepository->findOrAdd($payment->user->id, $payment->payment_gateway->id, $cid);

        return $this->insert([
            'cid' => $paymentMethod->external_token,
            'created_at' => $this->getNow(),
            'updated_at' => $this->getNow(),
            'charge_at' => $chargeAt,
            'payment_method_id' => $paymentMethod->id,
            'payment_gateway_id' => $payment->payment_gateway->id,
            'subscription_type_id' => $payment->subscription_type_id,
            'custom_amount' => $customAmount,
            'retries' => $retries,
            'user_id' => $payment->user->id,
            'parent_payment_id' => $payment->id,
            'state' => self::STATE_ACTIVE,
            'note' => $note,
        ]);
    }

    final public function createFromPayment(
        ActiveRow $payment,
        string $recurrentToken,
        ?\DateTime $chargeAt = null,
        ?float $customChargeAmount = null
    ): ?ActiveRow {
        if (!in_array($payment->status, [PaymentsRepository::STATUS_PAID, PaymentsRepository::STATUS_PREPAID], true)) {
            Debugger::log(
                "Could not create recurrent payment from payment [{$payment->id}], invalid payment status: [{$payment->status}]",
                Debugger::ERROR
            );
            return null;
        }

        // check if recurrent payment already exists and return existing instance
        $recurrentPayment = $this->recurrent($payment);
        if ($recurrentPayment) {
            return $recurrentPayment;
        }

        $retriesConfig = $this->applicationConfig->get('recurrent_payment_charges');
        if ($retriesConfig) {
            $retries = count(explode(',', $retriesConfig));
        } else {
            $retries = 1;
        }

        if (!$chargeAt) {
            try {
                $chargeAt = $this->calculateChargeAt($payment);
            } catch (\Exception $e) {
                Debugger::log($e, Debugger::ERROR);
                return null;
            }
        }

        $paymentMethod = $this->paymentMethodsRepository->findOrAdd(
            $payment->user->id,
            $payment->payment_gateway->id,
            $recurrentToken,
        );

        $recurrentPayment = $this->add(
            $paymentMethod,
            $payment,
            $chargeAt,
            $customChargeAmount,
            --$retries,
        );

        $this->emitter->emit(new RecurrentPaymentCreatedEvent($recurrentPayment));
        return $recurrentPayment;
    }

    final public function update(ActiveRow &$row, $data)
    {
        $fireEvent = false;
        if (isset($data['state']) && $data['state'] !== $row->state) {
            $fireEvent = true;
        }

        // Backwards compatibility for old `cid` field
        if (isset($data['cid']) && $data['cid'] !== $row->payment_method->external_token) {
            $paymentMethod = $this->paymentMethodsRepository->findOrAdd($row->user_id, $row->payment_gateway_id, $data['cid']);
            $data['payment_method_id'] = $paymentMethod->id;
        }

        $data['updated_at'] = $this->getNow();
        $result = parent::update($row, $data);

        if ($fireEvent) {
            $this->emitter->emit(new RecurrentPaymentStateChangedEvent($row));
            $this->hermesEmitter->emit(new HermesMessage('recurrent-payment-state-changed', [
                'recurrent_payment_id' => $row->id,
            ]), HermesMessage::PRIORITY_HIGH);
        }

        return $result;
    }

    final public function setCharged(ActiveRow $recurrentPayment, $payment, $status, $approval)
    {
        $fireEvent = true;
        if ($recurrentPayment->state === self::STATE_CHARGED) {
            $fireEvent = false;
        }

        $this->update($recurrentPayment, [
            'payment_id' => $payment->id,
            'state' => self::STATE_CHARGED,
            'status' => $status,
            'approval' => $approval,
        ]);

        if ($fireEvent) {
            $this->emitter->emit(new RecurrentPaymentRenewedEvent($recurrentPayment));
            $this->hermesEmitter->emit(new HermesMessage('recurrent-payment-renewed', [
                'recurrent_payment_id' => $recurrentPayment->id,
            ]), HermesMessage::PRIORITY_HIGH);
        }
    }

    final public function getChargeablePayments()
    {
        return $this->getTable()
            ->where('status IS NULL')
            ->where('retries >= 0')
            ->where('state = "active"')
            ->where(['charge_at <= ?' => $this->getNow()->modify('+15 minutes')])
            ->order('RAND()');
    }


    /**
     * @param $userId
     * @return Selection
     */
    final public function getUserActiveRecurrentPayments($userId)
    {
        return $this->getTable()
            ->where([
                'state' => RecurrentPaymentsRepository::STATE_ACTIVE,
                'recurrent_payments.user_id' => $userId,
            ])
            ->where('status IS NULL')
            ->where('retries >= 0')
            ->order('charge_at DESC');
    }

    /**
     * @param $userId
     * @return Selection
     */
    final public function userRecurrentPayments($userId)
    {
        return $this->getTable()
            ->where(['recurrent_payments.user_id' => $userId]);
    }

    final public function reactivateByUser($id, $userId)
    {
        $rp = $this->getTable()->where(['user_id' => $userId, 'id' => $id])->fetch();
        if ($rp == null) {
            return null;
        }
        $this->update($rp, [
            'state' => self::STATE_ACTIVE,
            'payment_id' => null,
            'status' => null,
            'approval' => null,
        ]);
        return $rp;
    }

    /**
     * @return ActiveRow|null Returns new recurrent payment if reactivation was successful.
     * @throws Exception Throws Exception if reactivation is unavailable.
     */
    final public function reactivateSystemStopped(ActiveRow $recurrentPayment): ?ActiveRow
    {
        if (!$this->canBeReactivatedAfterSystemStopped($recurrentPayment)) {
            throw new Exception("Reactivation of recurrent payment unavailable. Recurrent payment ID [{$recurrentPayment->id}] wasn't stopped by system.");
        }

        // load retries from config
        $retriesConfig = $this->applicationConfig->get('recurrent_payment_charges');
        if ($retriesConfig) {
            $retries = count(explode(',', $retriesConfig));
        } else {
            $retries = 1;
        }

        $newRecurrentPayment = $this->add(
            $recurrentPayment->payment_method,
            $recurrentPayment->payment,
            (new DateTime())->modify('+24 hours'),
            $recurrentPayment->custom_amount,
            $retries - 1,
            "Recurrent payment created by reactivation of system stopped recurrent payment [{$recurrentPayment->id}].",
        );
        return ($newRecurrentPayment instanceof ActiveRow) ? $newRecurrentPayment : null;
    }

    final public function canBeReactivatedAfterSystemStopped(ActiveRow $recurrentPayment): bool
    {
        // if recurrent payment cannot be stopped (by user or admin), we won't be able to reactivate when stopped by system
        if (!$this->canBeStopped($recurrentPayment)) {
            return false;
        }

        // only recurrent payments stopped by system after using all retries can be reactivated
        if ($recurrentPayment->state !== RecurrentPaymentsRepository::STATE_SYSTEM_STOP || $recurrentPayment->retries > 0) {
            return false;
        }

        // do not allow reactivation of old recurrent profiles
        if ($recurrentPayment->charge_at < (new DateTime())->modify('-14 days')) {
            return false;
        }
        return true;
    }

    final public function stoppedByUser($id, $userId)
    {
        $rp = $this->getTable()->where(['user_id' => $userId, 'id' => $id])->fetch();
        if ($rp == null) {
            return null;
        }

        if (!($this->canBeStoppedByUser($rp))) {
            throw new Exception('Recurrent payment ID ' . $rp->id . ' cannot be stopped by user');
        }

        $this->update($rp, ['state' => self::STATE_USER_STOP]);
        $this->emitter->emit(new RecurrentPaymentStoppedByUserEvent($rp));
        return $rp;
    }

    final public function stoppedByGDPR($userId)
    {
        $rps = $this->getTable()->where([
            'user_id' => $userId,
            'state' => 'active'])->fetchAll();

        foreach ($rps as $rp) {
            $this->update($rp, ['state' => self::STATE_USER_STOP]);
            $this->emitter->emit(new RecurrentPaymentStoppedByUserEvent($rp));
        }

        return true;
    }

    final public function stoppedByAdmin($id)
    {
        $rp = $this->find($id);
        if ($rp == null) {
            return null;
        }
        if (!($this->canBeStopped($rp))) {
            throw new Exception('Recurrent payment ID ' . $rp->id . ' cannot be stopped by admin');
        }

        $this->update($rp, ['state' => self::STATE_ADMIN_STOP]);
        $this->emitter->emit(new RecurrentPaymentStoppedByAdminEvent($rp));
        return $rp;
    }

    final public function stoppedBySystem($id)
    {
        $rp = $this->find($id);
        if ($rp == null) {
            return null;
        }
        $this->update($rp, ['state' => self::STATE_SYSTEM_STOP]);
        return $rp;
    }

    final public function getChargableBefore($date)
    {
        return $this->getTable()
            ->where('charge_at < ?', $date);
    }

    final public function all($problem = null, $subscriptionType = null, $status = null, string $cid = null)
    {
        $where = [];
        if ($subscriptionType) {
            $where['subscription_type_id'] = $subscriptionType;
        }
        if ($status) {
            $where['status'] = $status;
        }
        if ($problem) {
            $where['state'] = [self::STATE_SYSTEM_STOP, self::STATE_CHARGE_FAILED];
        }
        if ($cid) {
            $where['payment_method.external_token'] = $cid;
        }
        return $this->getTable()->where($where)->order('recurrent_payments.charge_at DESC, recurrent_payments.created_at DESC');
    }

    final public function getStatusPairs()
    {
        return $this->getTable()->select('status')->group('status')->fetchPairs('status', 'status');
    }

    final public function isStoppedBySubscription(ActiveRow $subscription)
    {
        $payment = $this->database->table('payments')->where(['subscription_id' => $subscription->id])->limit(1)->fetch();
        if ($payment) {
            $recurrent = $this->recurrent($payment);
            return $this->isStopped($recurrent);
        }
        return false;
    }

    final public function isStopped($recurrent)
    {
        if (!$recurrent) {
            return true;
        }
        if (in_array($recurrent->state, [self::STATE_SYSTEM_STOP, self::STATE_USER_STOP, self::STATE_ADMIN_STOP], true)) {
            return true;
        }
        if ($recurrent->state == self::STATE_CHARGE_FAILED) {
            // najdeme najnovsi rekurent s tymto cid a zistime ci je stopnuty
            $newRecurrent = $this->getTable()->where([
                    'payment_method.external_token' => $recurrent->payment_method->external_token,
                    'charge_at > ' => $recurrent->charge_at,
                ])
                ->order('charge_at DESC')
                ->limit(1)
                ->fetch();
            if ($newRecurrent && in_array($newRecurrent->state, [self::STATE_SYSTEM_STOP, self::STATE_USER_STOP, self::STATE_ADMIN_STOP], true)) {
                return true;
            }
        }
        return false;
    }

    final public function hasUserStopped($userId)
    {
        return $this->getTable()->where(['user_id' => $userId, 'state' => self::STATE_USER_STOP])->count('*');
    }

    final public function recurrent(ActiveRow $payment)
    {
        return $payment->related('recurrent_payments', 'parent_payment_id')->fetch();
    }

    final public function findByPayment(ActiveRow $payment)
    {
        return $payment->related('recurrent_payments', 'payment_id')->fetch();
    }

    final public function getLastWithState(ActiveRow $recurrentPayment, $state)
    {
        return $this->getTable()->where([
            'payment_method.external_token' => $recurrentPayment->payment_method->external_token,
            'recurrent_payments.payment_gateway_id' => $recurrentPayment->payment_gateway_id,
            'recurrent_payments.user_id' => $recurrentPayment->user_id,
            'state' => $state,
        ])->order('charge_at DESC')->fetch();
    }

    final public function getDuplicate()
    {
        return $this->getTable()
            ->select('COUNT(*) AS payments')
            ->select('user_id')
            ->where('charge_at > ?', $this->getNow())
            ->where('state = ?', 'active')
            ->group('user_id')
            ->having('payments > 1')
            ->fetchAll();
    }

    /**
     * @throws Exception If calculated next charge at date is invalid (before subscription's start date / in past)
     */
    final public function calculateChargeAt($payment)
    {
        $subscriptionType = $payment->subscription_type;
        $subscription = $payment->subscription;

        if (!$subscription) {
            $endTime = (clone $payment->paid_at)->add(new \DateInterval("P{$payment->subscription_type->length}D"));
        } else {
            $endTime = clone $subscription->end_time;
        }

        if ($endTime <= $this->getNow()) {
            throw new Exception(
                "Calculated next charge of recurrent payment would be in the past." .
                " Check payment [{$payment->id}] and subscription [{$subscription?->id}]."
            );
        }

        $chargeBefore = $subscriptionType->recurrent_charge_before;
        if ($chargeBefore === null) {
            $configSetting = $this->applicationConfig->get('recurrent_charge_before');
            if ($configSetting !== null && $configSetting !== '') {
                $validatedSetting = filter_var($configSetting, FILTER_VALIDATE_INT);
                if ($validatedSetting !== false && $validatedSetting >= 0) {
                    $chargeBefore = $validatedSetting;
                } else {
                    Debugger::log("Global setting [recurrent_charge_before] ignored due to invalid value: " . $configSetting, Debugger::WARNING);
                }
            }
        }
        if ($chargeBefore) {
            if (!$subscription) {
                // charge before is not allowed for payments without subscription, because in this case:
                // `charge_at` is calculated from `payment->paid_at`, so with chargeBefore set to value other than zero
                // payments would be shifted by `chargeBefore` value, every time we charge
                throw new Exception(
                    "Trying to set chargeBefore for payment charge without subscription." .
                    " Check payment [{$payment->id}]."
                );
            }

            if ($chargeBefore < 0) {
                $chargeBefore = abs($chargeBefore);
                $newEndTime = (clone $endTime)->add(new \DateInterval("PT{$chargeBefore}H"));
            } else {
                $newEndTime = (clone $endTime)->sub(new \DateInterval("PT{$chargeBefore}H"));
            }

            if ($newEndTime < $subscription->start_time) {
                throw new Exception(
                    "Calculated next charge of recurrent payment would be before subscription's start time." .
                    " Check payment [{$payment->id}] and subscription [{$subscription->id}]."
                );
            }
            if ($newEndTime < $payment->paid_at) {
                throw new Exception(
                    "Calculated next charge of recurrent payment would be before payment's paid_at time." .
                    " Check payment [{$payment->id}]."
                );
            }
            if ($newEndTime <= $this->getNow()) {
                throw new Exception(
                    "Calculated next charge of recurrent payment would be in the past." .
                    " Check payment [{$payment->id}] and subscription [{$subscription->id}]."
                );
            }
            $endTime = $newEndTime;
        }

        return $endTime;
    }

    final public function getStates()
    {
        return [
            self::STATE_USER_STOP,
            self::STATE_ADMIN_STOP,
            self::STATE_ACTIVE,
            self::STATE_PENDING,
            self::STATE_CHARGED,
            self::STATE_CHARGE_FAILED,
            self::STATE_SYSTEM_STOP,
        ];
    }

    final public function canBeStoppedByUser($recurrentPayment): bool
    {
        if ($this->paymentGatewayMetaRepository->hasValue(
            $recurrentPayment->payment_gateway,
            Gateway::META_USER_UNSTOPPABLE,
            '1'
        )) {
            return false;
        }
        return $this->canBeStopped($recurrentPayment);
    }

    final public function canBeStopped($recurrentPayment): bool
    {
        if ($this->paymentGatewayMetaRepository->hasValue(
            $recurrentPayment->payment_gateway,
            Gateway::META_UNSTOPPABLE,
            '1'
        )) {
            return false;
        }

        // TODO: Consider deprecation of this check in favor of unstoppable flags in the next major release.
        return $recurrentPayment->parent_payment->status !== PaymentsRepository::STATUS_PREPAID;
    }

    final public function latestSuccessfulRecurrentPayment(ActiveRow $recurrentPayment): ?ActiveRow
    {
        $parentPayment = $recurrentPayment->parent_payment;
        if (!$parentPayment) {
            return null;
        }

        if (in_array($parentPayment->status, [PaymentsRepository::STATUS_PAID, PaymentsRepository::STATUS_PREPAID], true)) {
            return $recurrentPayment;
        }

        $previousRecurrentCharge = $this->findByPayment($parentPayment);
        if (!$previousRecurrentCharge) {
            return null;
        }

        return $this->latestSuccessfulRecurrentPayment($previousRecurrentCharge);
    }

    final public function activeFirstChargeBetween(DateTime $chargeAtFrom, DateTime $chargeAtTo)
    {
        $where = [
            'state' => self::STATE_ACTIVE,
            'charge_at >=' => $chargeAtFrom,
            'charge_at <=' => $chargeAtTo,
            'parent_payment_id.status' => [PaymentsRepository::STATUS_PAID, PaymentsRepository::STATUS_PREPAID]
        ];

        return $this->getTable()->where($where);
    }

    final public function hasStoredCard(ActiveRow $user, ActiveRow $paymentGateway): bool
    {
        if (!$paymentGateway->is_recurrent) {
            return false;
        }

        // Only gateways supporting recurrent payments have support for stored cards
        $gateway = $this->gatewayFactory->getGateway($paymentGateway->code);
        if (!$gateway instanceof RecurrentPaymentInterface) {
            return false;
        }

        $usableRecurrents = $this->userRecurrentPayments($user->id)
            ->where(['payment_gateway.code = ?' => $paymentGateway->code])
            ->where(['expires_at > ?' => new DateTime()])
            ->where('state != ?', self::STATE_SYSTEM_STOP)
            ->order('id DESC, charge_at DESC');

        foreach ($usableRecurrents as $usableRecurrent) {
            if (($gateway instanceof ReusableCardPaymentInterface) && !$gateway->isCardReusable($usableRecurrent)) {
                continue;
            }
            return true;
        }

        return false;
    }

    final public function totalCount($allowCached = false, $forceCacheUpdate = false): int
    {
        $callable = function () {
            return parent::totalCount();
        };
        if ($allowCached) {
            return (int) $this->cacheRepository->loadAndUpdate(
                'recurrent_payments_count',
                $callable,
                \Nette\Utils\DateTime::from(CacheRepository::REFRESH_TIME_5_MINUTES),
                $forceCacheUpdate
            );
        }
        return $callable();
    }
}
