<?php

namespace Crm\PaymentsModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Repositories\AuditLogRepository;
use Crm\PaymentsModule\Models\VariableSymbolVariant;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\Database\Table\Selection;

class ParsedMailLogsRepository extends Repository
{
    /** @deprecated Use \Crm\PaymentsModule\Models\ParsedMailLog\StateEnum::values() enum instead. */
    public const ALL_STATES = [
        ParsedMailLogsRepository::STATE_WITHOUT_VS,
        ParsedMailLogsRepository::STATE_ALREADY_PAID,
        ParsedMailLogsRepository::STATE_CHANGED_TO_PAID,
        ParsedMailLogsRepository::STATE_PAYMENT_NOT_FOUND,
        ParsedMailLogsRepository::STATE_DIFFERENT_AMOUNT,
        ParsedMailLogsRepository::STATE_AUTO_NEW_PAYMENT,
        ParsedMailLogsRepository::STATE_DUPLICATED_PAYMENT,
        ParsedMailLogsRepository::STATE_ALREADY_REFUNDED,
    ];

    /** @deprecated Use \Crm\PaymentsModule\Models\ParsedMailLog\StateEnum::WithoutVs enum instead. */
    const STATE_WITHOUT_VS = 'without_vs';
    /** @deprecated Use \Crm\PaymentsModule\Models\ParsedMailLog\StateEnum::AlreadyPaid enum instead. */
    const STATE_ALREADY_PAID = 'already_paid';
    /** @deprecated Use \Crm\PaymentsModule\Models\ParsedMailLog\StateEnum::DuplicatedPayment enum instead. */
    const STATE_DUPLICATED_PAYMENT = 'duplicated_payment';
    /** @deprecated Use \Crm\PaymentsModule\Models\ParsedMailLog\StateEnum::ChangedToPaid enum instead. */
    const STATE_CHANGED_TO_PAID = 'changed_to_paid';
    /** @deprecated Use \Crm\PaymentsModule\Models\ParsedMailLog\StateEnum::PaymentNotFound enum instead. */
    const STATE_PAYMENT_NOT_FOUND = 'payment_not_found';
    /** @deprecated Use \Crm\PaymentsModule\Models\ParsedMailLog\StateEnum::DifferentAmount enum instead. */
    const STATE_DIFFERENT_AMOUNT = 'different_amount';
    /** @deprecated Use \Crm\PaymentsModule\Models\ParsedMailLog\StateEnum::AutoNewPayment enum instead. */
    const STATE_AUTO_NEW_PAYMENT = 'auto_new_payment';
    /** @deprecated Use \Crm\PaymentsModule\Models\ParsedMailLog\StateEnum::NoSign enum instead. */
    const STATE_NO_SIGN = 'no_sign';
    /** @deprecated Use \Crm\PaymentsModule\Models\ParsedMailLog\StateEnum::NotValidSign enum instead. */
    const STATE_NOT_VALID_SIGN = 'no_valid_sign';
    /** @deprecated Use \Crm\PaymentsModule\Models\ParsedMailLog\StateEnum::AlreadyRefunded enum instead. */
    const STATE_ALREADY_REFUNDED = 'already_refunded';

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
        ?int $amountFrom = null,
        ?int $amountTo = null,
    ): Selection {
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
            ->where('payment.status = ?', PaymentsRepository::STATUS_FORM)
            ->order('created_at DESC');

        if ($deliveredFrom) {
            $wrongAmountPaymentLogs->where('delivered_at >= ?', $deliveredFrom);
        }

        return $wrongAmountPaymentLogs->fetchAll();
    }
}
