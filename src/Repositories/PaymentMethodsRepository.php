<?php

namespace Crm\PaymentsModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Models\NowTrait;
use Nette\Database\Table\ActiveRow;

class PaymentMethodsRepository extends Repository
{
    use NowTrait;

    protected $tableName = 'payment_methods';

    final public function add(int $userId, int $paymentGatewayId, string $externalToken): ActiveRow|bool
    {
        return $this->insert([
            'user_id' => $userId,
            'payment_gateway_id' => $paymentGatewayId,
            'external_token' => $externalToken,
            'created_at' => $this->getNow(),
            'updated_at' => $this->getNow(),
        ]);
    }

    final public function findByExternalToken(int $userId, string $externalToken): ?ActiveRow
    {
        return $this->getTable()->where([
            'user_id' => $userId,
            'external_token' => $externalToken,
        ])->fetch();
    }

    final public function findAllForUser(int $userId): ?array
    {
        return $this->getTable()->where([
            'user_id' => $userId,
        ])->fetchAll();
    }

    final public function findOrAdd(int $userId, int $paymentGatewayId, string $externalToken): ActiveRow
    {
        $card = $this->findByExternalToken($userId, $externalToken);
        if ($card) {
            return $card;
        }

        return $this->add($userId, $paymentGatewayId, $externalToken);
    }
}
