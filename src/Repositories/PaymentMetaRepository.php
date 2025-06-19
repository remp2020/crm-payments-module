<?php

namespace Crm\PaymentsModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Models\NowTrait;
use Crm\ApplicationModule\Repositories\AuditLogRepository;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class PaymentMetaRepository extends Repository
{
    use NowTrait;
    public function __construct(
        Explorer $database,
        AuditLogRepository $auditLogRepository,
    ) {
        parent::__construct($database);
        $this->auditLogRepository = $auditLogRepository;
    }

    protected $tableName = 'payment_meta';

    /**
     * @param ActiveRow $payment
     * @param string $key
     * @param string $value
     * @param bool $override
     * @return ActiveRow
     */
    final public function add(ActiveRow $payment, $key, $value, $override = true)
    {
        $now = $this->getNow();
        if ($override && $this->exists($payment, $key)) {
            $this->getTable()->where([
                'payment_id' => $payment->id,
                'key' => $key,
            ])->update([
                'value' => $value,
                'updated_at' => $now,
            ]);
            return $this->values($payment, $key)->fetch();
        }
        return $this->insert([
            'payment_id' => $payment->id,
            'key' => $key,
            'value' => $value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param ActiveRow $payment
     * @param array $keys
     * @return Selection
     */
    final public function values(ActiveRow $payment, ...$keys)
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
    final public function exists(ActiveRow $payment, $key)
    {
        return $this->getTable()->where([
                'payment_id' => $payment->id,
                'key' => $key,
            ])->count('*') > 0;
    }

    /**
     * @param string $key
     * @param string $value
     * @return bool
     */
    final public function existsKeyValue(string $key, string $value): bool
    {
        return $this->getTable()->where([
                'key' => $key,
                'value' => $value,
            ])->count('*') > 0;
    }

    /**
     * @param ActiveRow $payment
     * @param string $key
     * @return int
     */
    final public function remove(ActiveRow $payment, $key)
    {
        return $this->getTable()->where([
            'payment_id' => $payment->id,
            'key' => $key,
        ])->delete();
    }

    final public function findByKeyAndValue(string $key, string $value): Selection
    {
        return $this->getTable()->where([
            'key' => $key,
            'value' => $value,
        ]);
    }

    final public function findByMeta(string $key, string $value)
    {
        return $this->getTable()->where([
            'key' => $key,
            'value' => $value,
        ])->fetch();
    }

    final public function findAllByMeta(string $key, string $value)
    {
        return $this->getTable()->where([
            'key' => $key,
            'value' => $value,
        ])
            ->order('id DESC')
            ->fetchAll();
    }

    /**
     * @param ActiveRow $payment
     * @param string $key
     */
    final public function findByPaymentAndKey(ActiveRow $payment, string $key)
    {
        return $this->getTable()->where([
            'payment_id' => $payment->id,
            'key' => $key,
        ])->fetch();
    }
}
