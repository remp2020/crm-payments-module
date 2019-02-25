<?php

namespace Crm\PaymentsModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\RetentionData;

class PaymentLogsRepository extends Repository
{
    use RetentionData;
    
    protected $tableName = 'payment_logs';

    public function add($status, $message, $sourceUrl, $paymentId = null)
    {
        return $this->insert([
            'status' => $status,
            'created_at' => new \DateTime(),
            'message' => $message,
            'source_url' => $sourceUrl,
            'payment_id' => $paymentId,
        ]);
    }
}
