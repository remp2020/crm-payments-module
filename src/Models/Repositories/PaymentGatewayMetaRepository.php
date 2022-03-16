<?php

namespace Crm\PaymentsModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class PaymentGatewayMetaRepository extends Repository
{
    protected $tableName = 'payment_gateway_meta';

    final public function add(ActiveRow $paymentGateway, string $key, string $value): void
    {
        $this->insert([
            'payment_gateway_id' => $paymentGateway->id,
            'key' => $key,
            'value' => $value,
        ]);
    }

    final public function upsert(ActiveRow $paymentGateway, string $key, string $value): void
    {
        $paymentItemMeta = $this->findByPaymentGatewayAndKey($paymentGateway, $key)->fetch();
        if ($paymentItemMeta) {
            $this->update($paymentGateway, ['key' => $key, 'value' => $value]);
        } else {
            $this->add($paymentGateway, $key, $value);
        }
    }

    final public function hasValue(ActiveRow $paymentGateway, string $key, string $value): bool
    {
        $meta = $this->findByPaymentGatewayItem($paymentGateway)->where('key', $key)->fetch();
        if (!$meta) {
            return false;
        }
        return $meta->value === $value;
    }

    final public function findByPaymentGatewayAndKey(ActiveRow $paymentGateway, string $key): Selection
    {
        return $this->findByPaymentGatewayItem($paymentGateway)->where('key', $key);
    }

    final public function findByPaymentGatewayItem(ActiveRow $paymentGateway): Selection
    {
        return $this->getTable()->where(['payment_gateway_id' => $paymentGateway->id]);
    }
}
