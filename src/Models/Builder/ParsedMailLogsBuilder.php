<?php

namespace Crm\PaymentsModule\Models\Builder;

use Crm\ApplicationModule\Builder\Builder;
use Nette\Database\Table\ActiveRow;

class ParsedMailLogsBuilder extends Builder
{
    protected $tableName = 'parsed_mail_logs';

    public function isValid()
    {
        if (!$this->get('delivered_at')) {
            $this->addError('missing required parameter: delivered_at');
            return false;
        }
        if (!$this->exists('state')) {
            $this->addError('missing required parameter: state');
        }

        if (count($this->getErrors()) > 0) {
            return false;
        }
        return true;
    }

    protected function setDefaults()
    {
        $this->set('created_at', new \DateTime());
    }

    public function setDeliveredAt($datetime)
    {
        return $this->set('delivered_at', $datetime);
    }

    public function setVariableSymbol($vs)
    {
        return $this->set('variable_symbol', $vs);
    }

    public function setAmount($amount)
    {
        return $this->set('amount', $amount);
    }

    public function setPayment(ActiveRow $payment)
    {
        return $this->set('payment_id', $payment->id);
    }

    public function setState($state)
    {
        return $this->set('state', $state);
    }

    public function setMessage($message)
    {
        return $this->set('message', $message);
    }

    public function setSourceAccountNumber(?string $sourceAccountNumber): self
    {
        return $this->set('source_account_number', $sourceAccountNumber);
    }
}
