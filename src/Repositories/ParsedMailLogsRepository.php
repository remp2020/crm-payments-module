<?php

namespace Crm\PaymentsModule\Repositories;

use Crm\ApplicationModule\Repository;
use Crm\PaymentsModule\Models\VariableSymbolVariant;
use Nette\Caching\Storage;
use Nette\Database\Explorer;

class ParsedMailLogsRepository extends Repository
{
    const STATE_WITHOUT_VS = 'without_vs';
    const STATE_ALREADY_PAID = 'already_paid';
    const STATE_DUPLICATED_PAYMENT = 'duplicated_payment';
    const STATE_CHANGED_TO_PAID = 'changed_to_paid';
    const STATE_PAYMENT_NOT_FOUND = 'payment_not_found';
    const STATE_DIFFERENT_AMOUNT = 'different_amount';
    const STATE_AUTO_NEW_PAYMENT = 'auto_new_payment';
    const STATE_NO_SIGN = 'no_sign';
    const STATE_NOT_VALID_SIGN = 'no_valid_sign';
    const STATE_ALREADY_REFUNDED = 'already_refunded';

    protected $tableName = 'parsed_mail_logs';

    public function __construct(
        Explorer $database,
        Storage $cacheStorage = null
    ) {
        parent::__construct($database, $cacheStorage);
    }

    public function all(?string $vs = null, ?string $state = null, ?string $paymentStatus = null)
    {
        $where = [];
        if ($vs !== null) {
            $where["{$this->tableName}.variable_symbol LIKE ?"] = "%{$vs}%";
        }
        if ($state !== null) {
            $where["{$this->tableName}.state"] = $state;
        }
        if ($paymentStatus !== null) {
            $where['payment.status'] = $paymentStatus;
        }
        return $this->getTable()->where($where)->order('parsed_mail_logs.created_at DESC');
    }

    public function findByVariableSymbols($variableSymbols)
    {
        $variableSymbolVariants = new VariableSymbolVariant();
        $variableSymbols = $variableSymbolVariants->variableSymbolsVariants($variableSymbols);
        return $this->getTable()->where(['variable_symbol' => $variableSymbols])->order('delivered_at DESC');
    }

    public function lastLog()
    {
        return $this->getTable()->order('created_at DESC')->limit(1)->fetch();
    }

    public function getDifferentAmountPaymentLogs(?\DateTime $deliveredFrom): array
    {
        $wrongAmountPaymentLogs = $this->getTable()
            ->where('state = ?', 'different_amount')
            ->where('payment.status = ?', PaymentsRepository::STATUS_FORM)
            ->order('created_at DESC');

        if ($deliveredFrom) {
            $wrongAmountPaymentLogs->where('delivered_at >= ?', $deliveredFrom);
        }

        return $wrongAmountPaymentLogs->fetchAll();
    }
}
