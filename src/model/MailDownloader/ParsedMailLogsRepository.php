<?php

namespace Crm\PaymentsModule\MailConfirmation;

use Crm\ApplicationModule\Repository;
use Crm\PaymentsModule\VariableSymbolVariant;

class ParsedMailLogsRepository extends Repository
{
    const STATE_WITHOUT_VS = 'without_vs';
    const STATE_ALREADY_PAID = 'already_paid';
    const STATE_CHANGED_TO_PAID = 'changed_to_paid';
    const STATE_PAYMENT_NOT_FOUND = 'payment_not_found';
    const STATE_DIFFERENT_AMOUNT = 'different_amount';
    const STATE_AUTO_NEW_PAYMENT = 'auto_new_payment';
    const STATE_NO_SIGN = 'no_sign';
    const STATE_NOT_VALID_SIGN = 'no_valid_sign';

    protected $tableName = 'parsed_mail_logs';

    public function all($vs = '', $state = '')
    {
        $where = [];
        if ($vs) {
            $where['variable_symbol LIKE ?'] = "%{$vs}%";
        }
        if ($state) {
            $where['state'] = $state;
        }
        return $this->getTable()->where($where)->order('delivered_at DESC');
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
}
