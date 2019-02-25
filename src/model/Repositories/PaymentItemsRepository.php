<?php

namespace Crm\PaymentsModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Table\IRow;
use Nette\Utils\DateTime;

class PaymentItemsRepository extends Repository
{
    protected $tableName = 'payment_items';

    public function add(IRow $payment, $name, $amount, $vat, $subscriptionTypeId)
    {
        return $this->insert([
            'payment_id' => $payment->id,
            'subscription_type_id' => $subscriptionTypeId,
            'name' => $name,
            'amount' => $amount,
            'vat' => $vat,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ]);
    }

    public function deleteForPaymentId(int $paymentId)
    {
        return $this->getTable()
                    ->where('payment_id', $paymentId)
                    ->delete();
    }
}
