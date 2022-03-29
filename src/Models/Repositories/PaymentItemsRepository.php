<?php

namespace Crm\PaymentsModule\Repository;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Selection;
use Crm\PaymentsModule\DataProvider\CanUpdatePaymentItemDataProviderInterface;
use Crm\PaymentsModule\Events\NewPaymentItemEvent;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\PaymentItem\PaymentItemInterface;
use Exception;
use League\Event\Emitter;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class PaymentItemsRepository extends Repository
{
    protected $tableName = 'payment_items';

    private $applicationConfig;

    private $emitter;

    private $paymentItemMetaRepository;

    private $dataProviderManager;

    public function __construct(
        Explorer $database,
        ApplicationConfig $applicationConfig,
        Emitter $emitter,
        Storage $cacheStorage = null,
        PaymentItemMetaRepository $paymentItemMetaRepository,
        DataProviderManager $dataProviderManager
    ) {
        parent::__construct($database, $cacheStorage);
        $this->applicationConfig = $applicationConfig;
        $this->emitter = $emitter;
        $this->paymentItemMetaRepository = $paymentItemMetaRepository;
        $this->dataProviderManager = $dataProviderManager;
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

    public function update(ActiveRow &$row, $data)
    {
        if (!($this->canBeUpdated($row))) {
            throw new Exception('Payment item ' . $row->id . ' cannot be updated');
        }

        $data['updated_at'] = new DateTime();
        return parent::update($row, $data);
    }

    final public function deleteByPayment(ActiveRow $payment)
    {
        // remove payment item meta
        $paymentItemMetas = $this->paymentItemMetaRepository->getTable()
            ->where(['payment_item.payment_id' => $payment->id])
            ->fetchAll();
        foreach ($paymentItemMetas as $paymentItemMeta) {
            $this->paymentItemMetaRepository->delete($paymentItemMeta);
        }

        $q = $this->getTable()
            ->where('payment_id', $payment->id);

        return $q->delete();
    }

    /**
     * @param ActiveRow $paymentItem
     * @return int
     */
    final public function deletePaymentItem(ActiveRow $paymentItem): int
    {
        // remove payment item meta
        $paymentItemMetas = $this->paymentItemMetaRepository->findByPaymentItem($paymentItem);
        foreach ($paymentItemMetas as $paymentItemMeta) {
            $this->paymentItemMetaRepository->delete($paymentItemMeta);
        }

        return $this->getTable()->where('id', $paymentItem->id)->delete();
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
