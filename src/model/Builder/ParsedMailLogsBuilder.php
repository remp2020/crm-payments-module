<?php

namespace Crm\PaymentsModule\Builder;

use Crm\ApplicationModule\Builder\Builder;
use Nette\Database\Table\IRow;

class ParsedMailLogsBuilder extends Builder
{
    protected $tableName = 'parsed_mail_logs';

    public function isValid()
    {
        if (!$this->get('delivered_at')) {
            $this->addError('Nebolo zadany cas poslania');
            return false;
        }
        if (!$this->exists('state')) {
            $this->addError('Nebola zadany stav');
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

    public function setPayment(IRow $payment)
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
}
