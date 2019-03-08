<?php

namespace Crm\PaymentsModule\Repository;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\Repository;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\ProductsModule\Events\CartItemAddedEvent;
use Crm\PaymentsModule\PaymentItem\PaymentItemInterface;
use Crm\ProductsModule\PaymentItem\ProductPaymentItem;
use Crm\ProductsModule\Repository\ProductsRepository;
use League\Event\Emitter;
use Nette\Caching\IStorage;
use Nette\Database\Context;
use Nette\Database\Table\IRow;
use Nette\Utils\DateTime;

class PaymentItemsRepository extends Repository
{
    protected $tableName = 'payment_items';

    private $productsRepository;

    private $applicationConfig;

    private $emitter;

    public function __construct(
        Context $database,
        ProductsRepository $productsRepository,
        ApplicationConfig $applicationConfig,
        Emitter $emitter,
        IStorage $cacheStorage = null
    ) {
        parent::__construct($database, $cacheStorage);
        $this->productsRepository = $productsRepository;
        $this->applicationConfig = $applicationConfig;
        $this->emitter = $emitter;
    }

    public function add(IRow $payment, PaymentItemContainer $container)
    {
        /** @var PaymentItemInterface $item */
        foreach ($container->items() as $item) {
            $data = [
                'payment_id' => $payment->id,
                'type' => $item->type(),
                'count' => $item->count(),
                'name' => $item->name(),
                'amount' => $item->price(),
                'vat' => $item->vat(),
                'created_at' => new DateTime(),
                'updated_at' => new DateTime(),
            ];
            foreach ($item->data() as $key => $value) {
                $data[$key] = $value;
            }
            $this->insert($data);
        }
    }
/*
    public function add(IRow $payment, string $name, $amount, int $vat, int $subscriptionTypeId = null, IRow $product = null, int $count = 1)
    {
        $type = 'subscription_type';
        if (!$subscriptionTypeId && $product) {
            $type = 'product';
        }

        $paymentItem = $this->insert([
            'payment_id' => $payment->id,
            'subscription_type_id' => $subscriptionTypeId,
            'product_id' => $product ? $product->id : null,
            'count' => $count,
            'name' => $name,
            'amount' => $amount,
            'vat' => $vat,
            'type' => $type,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ]);

        if ($product) {
            $this->emitter->emit(new CartItemAddedEvent($paymentItem->product));
        }

        return $paymentItem;
    }
*/

    public function deleteByPayment(IRow $payment)
    {
        return $this->getTable()
            ->where('payment_id', $payment->id)
            ->delete();
    }
}
