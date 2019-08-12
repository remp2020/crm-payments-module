<?php

namespace Crm\PaymentsModule\DataProvider;

use Nette\Database\Table\ActiveRow;
use Tracy\Debugger;

class PaymentInvoiceProviderManager
{
    /** @var PaymentInvoiceProviderInterface[] */
    private $providers = [];

    public function register(PaymentInvoiceProviderInterface $provider, $priority = 100)
    {
        if (isset($this->providers[$priority])) {
            do {
                $priority++;
            } while (isset($this->providers[$priority]));
        }
        $this->providers[$priority] = $provider;
    }

    /**
     * @return PaymentInvoiceProviderInterface[]
     */
    public function getProviders()
    {
        ksort($this->providers);
        return $this->providers;
    }

    /**
     * @return array
     */
    public function getAttachments(ActiveRow $payment): array
    {
        $attachments = [];
        foreach ($this->getProviders() as $provider) {
            try {
                $attachment = $provider->provide($payment);
            } catch (\Exception $e) {
                Debugger::log($e, Debugger::ERROR);
                continue;
            }

            if ($attachment === false) {
                Debugger::log("Unable to get invoice for payment [{$payment->variable_symbol}]", Debugger::ERROR);
                continue;
            }

            if (!isset($attachment['filename']) || empty(trim($attachment['filename']))) {
                Debugger::log("Invoice attachment for payment [{$payment->variable_symbol}] is missing filename.", Debugger::ERROR);
                continue;
            }

            if (!isset($attachment['content']) || empty(trim($attachment['content']))) {
                Debugger::log("Invoice attachment for payment [{$payment->variable_symbol}] is missing content.", Debugger::ERROR);
                continue;
            }

            if (!isset($attachment['mime_type']) || empty(trim($attachment['mime_type']))) {
                Debugger::log("Invoice attachment for payment [{$payment->variable_symbol}] is missing mime_type.", Debugger::ERROR);
                continue;
            }

            $attachments[] = $attachment;
        }
        return $attachments;
    }
}
