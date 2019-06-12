<?php

namespace Crm\PaymentsModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\IRow;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class PaymentMetaRepository extends Repository
{
    protected $tableName = 'payment_meta';

    /**
     * @param ActiveRow $payment
     * @param string $key
     * @param string $value
     * @param bool $override
     * @return \Nette\Database\Table\IRow
     */
    public function add(ActiveRow $payment, $key, $value, $override = true)
    {
        if ($override && $this->exists($payment, $key)) {
            $this->getTable()->where([
                'payment_id' => $payment->id,
                'key' => $key,
            ])->update([
                'value' => $value,
            ]);
            return $this->values($payment, $key)->fetch();
        }
        return $this->insert([
            'payment_id' => $payment->id,
            'key' => $key,
            'value' => $value,
        ]);
    }

    /**
     * @param ActiveRow $payment
     * @param array $keys
     * @return Selection
     */
    public function values(ActiveRow $payment, ...$keys)
    {
        return $this->getTable()->where([
            'payment_id' => $payment->id,
            'key' => $keys,
        ]);
    }

    /**
     * @param ActiveRow $payment
     * @param string $key
     * @return bool
     */
    public function exists(ActiveRow $payment, $key)
    {
        return $this->getTable()->where([
            'payment_id' => $payment->id,
            'key' => $key,
        ])->count('*') > 0;
    }

    /**
     * @param ActiveRow $payment
     * @param string $key
     * @return int
     */
    public function remove(ActiveRow $payment, $key)
    {
        return $this->getTable()->where([
            'payment_id' => $payment->id,
            'key' => $key,
        ])->delete();
    }

    public function findByMeta(string $key, string $value)
    {
        return $this->getTable()->where([
            'key' => $key,
            'value' => $value
        ])->fetch();
    }
}
