<?php

namespace Crm\PaymentsModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Table\IRow;
use Nette\Utils\DateTime;

class PaymentItemMetaRepository extends Repository
{
    protected $tableName = 'payment_item_meta';

    final public function addMetas(IRow $paymentItem, array $keyValue)
    {
        foreach ($keyValue as $key => $value) {
            $this->add($paymentItem, $key, $value);
        }
    }

    final public function add(IRow $paymentItem, $key, $value)
    {
        $this->insert([
            'payment_item_id' => $paymentItem->id,
            'key' => $key,
            'value' => $value,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime()
        ]);
    }

    final public function update(IRow &$row, $data)
    {
        $data['updated_at'] = new DateTime();

        return parent::update($row, $data);
    }

    final public function upsert(IRow $paymentItem, $key, $value)
    {
        $paymentItemMeta = $this->findByPaymentItemAndKey($paymentItem, $key)->fetch();
        if ($paymentItemMeta) {
            $this->update($paymentItemMeta, ['key' => $key, 'value' => $value]);
        } else {
            $this->add($paymentItem, $key, $value);
        }
    }

    final public function findByPaymentItemAndKey(IRow $paymentItem, string $key)
    {
        return $this->findByPaymentItem($paymentItem)
            ->where('key = ?', $key);
    }

    final public function findByPaymentItem(IRow $paymentItem)
    {
        return $this->getTable()->where(['payment_item_id' => $paymentItem->id]);
    }
}
