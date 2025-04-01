<?php

namespace Crm\PaymentsModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Repositories\AuditLogRepository;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\VariableSymbolVariant;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\Database\Table\Selection;

class ParsedMailLogsRepository extends Repository
{
    protected $tableName = 'parsed_mail_logs';

    public function __construct(
        Explorer $database,
        AuditLogRepository $auditLogRepository,
        Storage $cacheStorage = null,
    ) {
        parent::__construct($database, $cacheStorage);
        $this->auditLogRepository = $auditLogRepository;
    }

    public function all(
        ?string $vs = null,
        ?string $state = null,
        ?string $paymentStatus = null,
        ?float $amountFrom = null,
        ?float $amountTo = null,
        ?string $sourceAccountNumber = null,
    ): Selection {
        $where = [];
        if ($vs !== null) {
            $where["{$this->tableName}.variable_symbol LIKE ?"] = "%{$vs}%";
        }
        if ($sourceAccountNumber !== null) {
            $where["{$this->tableName}.source_account_number LIKE ?"] = "%{$sourceAccountNumber}%";
        }
        if ($state !== null) {
            $where["{$this->tableName}.state"] = $state;
        }
        if ($paymentStatus !== null) {
            $where['payment.status'] = $paymentStatus;
        }
        if ($amountFrom) {
            $where["{$this->tableName}.amount >= ?"] = $amountFrom;
        }
        if ($amountTo) {
            $where["{$this->tableName}.amount <= ?"] = $amountTo;
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
            ->where('payment.status = ?', PaymentStatusEnum::Form->value)
            ->order('created_at DESC');

        if ($deliveredFrom) {
            $wrongAmountPaymentLogs->where('delivered_at >= ?', $deliveredFrom);
        }

        return $wrongAmountPaymentLogs->fetchAll();
    }
}
