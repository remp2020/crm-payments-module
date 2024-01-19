<?php

namespace Crm\PaymentsModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
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
        $paymentGatewayMeta = $this->findByPaymentGatewayAndKey($paymentGateway, $key)->fetch();
        if ($paymentGatewayMeta) {
            $this->update($paymentGatewayMeta, ['key' => $key, 'value' => $value]);
        } else {
            $this->add($paymentGateway, $key, $value);
        }
    }

    final public function hasValue(ActiveRow $paymentGateway, string $key, string $value): bool
    {
        $meta = $this->findByPaymentGateway($paymentGateway)->where('key', $key)->fetch();
        if (!$meta) {
            return false;
        }
        return $meta->value === $value;
    }

    final public function findByPaymentGatewayAndKey(ActiveRow $paymentGateway, string $key): Selection
    {
        return $this->findByPaymentGateway($paymentGateway)->where('key', $key);
    }

    final public function findByPaymentGateway(ActiveRow $paymentGateway): Selection
    {
        return $paymentGateway->related('payment_gateway_meta');
    }
}
