<?php

namespace Crm\PaymentsModule\Repositories;

use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Crm\ApplicationModule\Selection;
use Crm\PaymentsModule\DataProviders\CanUpdatePaymentItemDataProviderInterface;
use Crm\PaymentsModule\Events\BeforeRemovePaymentItemEvent;
use Crm\PaymentsModule\Events\NewPaymentItemEvent;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemHelper;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemInterface;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Exception;
use League\Event\Emitter;
use Nette\Caching\Storage;
use Nette\Database\DriverException;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class PaymentItemsRepository extends Repository
{
    protected $tableName = 'payment_items';

    public function __construct(
        Explorer $database,
        private Emitter $emitter,
        private PaymentItemMetaRepository $paymentItemMetaRepository,
        private DataProviderManager $dataProviderManager,
        AuditLogRepository $auditLogRepository,
        private Explorer $dbContext,
        Storage $cacheStorage = null
    ) {
        parent::__construct($database, $cacheStorage);
        $this->auditLogRepository = $auditLogRepository;
    }

    final public function add(ActiveRow $payment, PaymentItemContainer $container): array
    {
        $rows = [];
        /** @var PaymentItemInterface $item */
        foreach ($container->items() as $item) {
            $data = [
                'payment_id' => $payment->id,
                'type' => $item->type(),
                'count' => $item->count(),
                'name' => $item->name(),
                'amount' => $item->unitPrice(),
                'amount_without_vat' => $item->unitPriceWithoutVAT(),
                'vat' => $item->vat(),
                'created_at' => new DateTime(),
                'updated_at' => new DateTime(),
            ];
            foreach ($item->data() as $key => $value) {
                $data[$key] = $value;
            }
            $row = $this->insert($data);
            $this->paymentItemMetaRepository->addMetas($row, $item->meta());

            $this->emitter->emit(new NewPaymentItemEvent($row));

            $rows[] = $row;
        }
        return $rows;
    }

    public function update(ActiveRow &$row, $data, $force = false)
    {
        if (!$force && !($this->canBeUpdated($row))) {
            throw new Exception('Payment item ' . $row->id . ' cannot be updated');
        }

        $data['updated_at'] = new DateTime();
        return parent::update($row, $data);
    }

    final public function deleteByPayment(ActiveRow $payment)
    {
        $this->dbContext->beginTransaction();

        try {
            // remove payment item meta
            $this->paymentItemMetaRepository->deleteByPayment($payment);

            $paymentItems = $this->getTable()
                ->where('payment_id', $payment->id);
            foreach ($paymentItems as $paymentItem) {
                $this->emitter->emit(new BeforeRemovePaymentItemEvent($paymentItem));
                $this->delete($paymentItem);
            }
        } catch (\Exception $exception) {
            $this->dbContext->rollBack();
            throw $exception;
        }

        $this->dbContext->commit();

        return true;
    }

    final public function deletePaymentItem(ActiveRow $paymentItem): bool
    {
        $inTransaction = false;
        try {
            $this->dbContext->beginTransaction();
            $inTransaction = true;
        } catch (DriverException $e) {
            // transaction already in progress, ignore exception
        }

        try {
            $this->paymentItemMetaRepository->deleteByPaymentItem($paymentItem);
            $this->emitter->emit(new BeforeRemovePaymentItemEvent($paymentItem));
            $result = $this->delete($paymentItem);
        } catch (\Exception $exception) {
            $this->dbContext->rollBack();
            throw $exception;
        }

        if ($inTransaction) {
            $this->dbContext->commit();
        }

        return $result;
    }

    /**
     * @param ActiveRow $payment
     * @return Selection
     */
    final public function getByPayment(ActiveRow $payment): Selection
    {
        return $this->getTable()->where('payment_id', $payment->id);
    }

    /**
     * @param ActiveRow $payment
     * @param string $paymentItemType
     * @return array|ActiveRow[]
     */
    final public function getByType(ActiveRow $payment, string $paymentItemType): array
    {
        return $payment->related('payment_items')->where('type = ?', $paymentItemType)->fetchAll();
    }

    final public function getTypes(): array
    {
        return $this->getTable()->select('DISTINCT type')->fetchPairs('type', 'type');
    }

    final public function copyPaymentItem(ActiveRow $paymentItem, ActiveRow $newPayment, bool $fromSubscriptionType = true)
    {
        $paymentItemArray = $paymentItem->toArray();

        $oldPaymentId = $paymentItemArray['payment_id'];
        $paymentItemArray['payment_id'] = $newPayment->id;
        $paymentItemArray['created_at'] = new DateTime();
        $paymentItemArray['updated_at'] = new DateTime();
        unset($paymentItemArray['id']);

        $newPaymentItemMetaArray = $paymentItem->related('payment_item_meta')->fetchPairs('key', 'value');

        if ($paymentItemArray['type'] === SubscriptionTypePaymentItem::TYPE && $paymentItem->subscription_type_item_id) {
            $subscriptionTypeItem = $paymentItem->subscription_type_item;
            if (!$subscriptionTypeItem) {
                throw new Exception("No `subscription_type_item`: ({$newPaymentItemMetaArray['subscription_type_item_id']}) found by copying payment: {$oldPaymentId} - to payment: {$newPayment->id}");
            }
            $subscriptionTypePaymentItem = SubscriptionTypePaymentItem::fromSubscriptionTypeItem($subscriptionTypeItem, $paymentItemArray['count']);

            if ($fromSubscriptionType) {
                $paymentItemArray['name'] = $subscriptionTypePaymentItem->name();
                $paymentItemArray['amount'] = $subscriptionTypePaymentItem->unitPrice();
                $paymentItemArray['vat'] = $subscriptionTypePaymentItem->vat();
                $paymentItemArray['amount_without_vat'] = $subscriptionTypePaymentItem->unitPriceWithoutVAT();
            } elseif ($subscriptionTypeItem->vat !== $paymentItemArray['vat']) {
                $paymentItemArray['vat'] = $subscriptionTypeItem->vat;
                $paymentItemArray['amount_without_vat'] = PaymentItemHelper::getPriceWithoutVAT(
                    unitPrice: $paymentItemArray['amount'],
                    vat: $subscriptionTypeItem->vat,
                );
            }
        }

        $newPaymentItem = $this->insert($paymentItemArray);
        $this->paymentItemMetaRepository->addMetas($newPaymentItem, $newPaymentItemMetaArray);
    }

    private function canBeUpdated($paymentItem): bool
    {
        /** @var CanUpdatePaymentItemDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('payments.payment_items.update', CanUpdatePaymentItemDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            if (!($provider->provide(['paymentItem' => $paymentItem]))) {
                return false;
            }
        }

        return true;
    }
}
