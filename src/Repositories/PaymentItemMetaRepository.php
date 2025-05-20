<?php

namespace Crm\PaymentsModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class PaymentItemMetaRepository extends Repository
{
    protected $tableName = 'payment_item_meta';

    final public function addMetas(ActiveRow $paymentItem, array $keyValue)
    {
        foreach ($keyValue as $key => $value) {
            $this->add($paymentItem, $key, $value);
        }
    }

    final public function add(ActiveRow $paymentItem, $key, $value)
    {
        $this->insert([
            'payment_item_id' => $paymentItem->id,
            'key' => $key,
            'value' => $value,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ]);
    }

    final public function update(ActiveRow &$row, $data)
    {
        $data['updated_at'] = new DateTime();

        return parent::update($row, $data);
    }

    final public function upsert(ActiveRow $paymentItem, $key, $value)
    {
        $paymentItemMeta = $this->findByPaymentItemAndKey($paymentItem, $key)->fetch();
        if ($paymentItemMeta) {
            $this->update($paymentItemMeta, ['key' => $key, 'value' => $value]);
        } else {
            $this->add($paymentItem, $key, $value);
        }
    }

    final public function findByPaymentItemAndKey(ActiveRow $paymentItem, string $key)
    {
        return $this->findByPaymentItem($paymentItem)
            ->where('key = ?', $key);
    }

    final public function findByPaymentItem(ActiveRow $paymentItem)
    {
        return $this->getTable()->where(['payment_item_id' => $paymentItem->id]);
    }

    final public function deleteByPayment(ActiveRow $payment)
    {
        $paymentItemMetas = $this->getTable()
            ->where(['payment_item.payment_id' => $payment->id]);

        foreach ($paymentItemMetas as $paymentItemMeta) {
            $this->delete($paymentItemMeta);
        }

        return true;
    }

    final public function deleteByPaymentItem(ActiveRow $paymentItem)
    {
        $paymentItemMetas = $this->getTable()
            ->where(['payment_item_id' => $paymentItem->id]);

        foreach ($paymentItemMetas as $paymentItemMeta) {
            $this->delete($paymentItemMeta);
        }

        return true;
    }
}
