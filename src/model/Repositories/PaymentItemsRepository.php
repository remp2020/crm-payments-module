<?php

namespace Crm\PaymentsModule\Repository;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\Repository;
use Crm\ProductsModule\Events\CartItemAddedEvent;
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

    public function deleteForPaymentId(int $paymentId)
    {
        return $this->getTable()
                    ->where('payment_id', $paymentId)
                    ->delete();
    }

    public function reset(IRow $payment, array $productIds = []): void
    {
        $this->getTable()->where([
            'payment_id' => $payment->id,
        ])->delete();
        foreach ($productIds as $productId => $count) {
            $product = $this->productsRepository->find($productId);
            $this->add($payment, $product->name, $product->price, 20, null, $product, $count);
        }
    }

    public function hasUniqueProduct(IRow $product, int $userId): bool
    {
        return $this->getTable()->where(['payment.user_id' => $userId, 'payment.status' => PaymentsRepository::STATUS_PAID, 'product_id' => $product->id])->count('*') > 0;
    }

    public function getProductSalesCount(IRow $product)
    {
        return $this->getTable()
            ->where('product_id', $product->id)
            ->where('payment.status', PaymentsRepository::STATUS_PAID)
            ->fetchField('COUNT(`count`)');
    }
}
