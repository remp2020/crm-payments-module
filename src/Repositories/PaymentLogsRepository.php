<?php

namespace Crm\PaymentsModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Models\Database\RetentionData;

class PaymentLogsRepository extends Repository
{
    use RetentionData;
    
    protected $tableName = 'payment_logs';

    final public function add($status, $message, $sourceUrl, $paymentId = null)
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
