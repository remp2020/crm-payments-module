<?php

namespace Crm\PaymentsModule\Gateways;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\PaymentsModule\Events\RecurrentPaymentRenewedEvent;
use Crm\PaymentsModule\GatewayFail;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Nette\Database\Table\IRow;
use Nette\Utils\DateTime;
use Tracy\Debugger;

class GoPayRecurrent extends BaseGoPay implements RecurrentPaymentInterface
{
    private $recurrenceDateTo;

    protected function initialize()
    {
        parent::initialize();
        $this->recurrenceDateTo = date('Y-m-d', strtotime('+10 years'));

        $recurrenceDateToConfig = $this->applicationConfig->get('gopay_recurrence_date_to');
        if ($recurrenceDateToConfig) {
            $this->recurrenceDateTo = $recurrenceDateToConfig;
        }
    }

    protected function preparePaymentData(IRow $payment): array
    {
        $data = parent::preparePaymentData($payment);
        $data['purchaseData']['recurrence'] = [
            'recurrence_cycle' => 'ON_DEMAND',
            'recurrence_date_to' => DateTime::from(strtotime($this->recurrenceDateTo))->format('Y-m-d'),
        ];
        return $data;
    }

    public function notification($id): bool
    {
        $this->initialize();

        $reference = $this->paymentMetaRepository->findByMeta('gopay_transaction_reference', $id);
        if (!$reference) {
            return false;
        }

        $payment = $reference->payment;

        $request = [
            'transactionReference' => $reference->value,
        ];

        $this->response = $this->gateway->completePurchase($request);

        $data = $this->response->getData();
        $this->setMeta($payment, $data);

        if ($payment->status != PaymentsRepository::STATUS_PAID && $data['state'] == self::STATE_PAID) {
            $this->paymentsRepository->updateStatus($payment, PaymentsRepository::STATUS_PAID, true);

            if ((boolean)$payment->payment_gateway->is_recurrent) {
                $recurrentPayment = $this->recurrentPaymentsRepository->recurrent($payment);
                if ($recurrentPayment) {
                    $this->recurrentPaymentsRepository->setCharged($recurrentPayment, $payment, 'OK', 'OK');
                } else {
                    $this->emitter->emit(new HermesMessage('create-recurrent-payment', [
                        'id' => $payment->id,
                        'token' => $this->getRecurrentToken(),
                    ]));
                }
            }
        }

        return true;
    }

    public function getRecurrentToken()
    {
        if (!isset($_GET['id'])) {
            throw new \Exception('Missing gopay payment id for recurrent');
        }

        return $_GET['id'];
    }

    public function hasRecurrentToken(): bool
    {
        return isset($_GET['id']);
    }

    public function charge($payment, $token)
    {
        $this->initialize();

        $paymentItems = $payment->related('payment_items');
        $items = $this->prepareItems($paymentItems);
        $description = $this->prepareDescription($paymentItems, $payment);

        $data = [
            'transactionReference' => $token,
            'purchaseData' => [
                'amount' => intval(round($payment->amount * 100)),
                'currency' => $this->applicationConfig->get('currency'),
                'order_number' => $payment->variable_symbol,
                'order_description' => $description,
                'items' => $items,
            ],
        ];

        if ($this->eetEnabled) {
            $data['purchaseData']['eet'] = $this->prepareEetItems($paymentItems);
        }

        try {
            $this->response = $this->gateway->recurrence($data);
        } catch (\Exception $exception) {
            Debugger::log($exception);
            throw new GatewayFail($exception->getMessage(), $exception->getCode());
        }
    }

    public function checkValid($token)
    {
        $this->initialize();
        $statusData = $this->gateway->status(['transactionReference' => $token]);
        $data = $statusData->getData();
        if (isset($data['recurrence'])) {
            $end = DateTime::from($data['recurrence']['recurrence_date_to']);
            return $end > new DateTime();
        }
        return false;
    }

    public function checkExpire($recurrentPayments)
    {
        $this->initialize();

        $result = [];
        foreach ($recurrentPayments as $recurrentPayment) {
            $statusData = $this->gateway->status(['transactionReference' => $recurrentPayment]);
            $data = $statusData->getData();
            if (isset($data['payer']['payment_card'])) {
                $expiration = $data['payer']['payment_card']['card_expiration'];
                $month = substr($expiration, 2, 2);
                $year = "20" . substr($expiration, 0, 2);
                $result[$recurrentPayment] = DateTime::from("$year-$month-01 00:00 next month");
            }
        }
        return $result;
    }

    public function getResultCode()
    {
        return $this->response->getData()['state'];
    }

    public function getResultMessage()
    {
        return $this->response->getData()['state'];
    }
}
