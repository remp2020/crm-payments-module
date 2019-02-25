<?php

namespace Crm\PaymentsModule\Hermes;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Nette\Database\Table\IRow;
use Psr\Log\LoggerAwareTrait;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

class CreateRecurrentPaymentHandler implements HandlerInterface
{
    use LoggerAwareTrait;

    /** @var PaymentsRepository */
    private $paymentsRepository;

    /** @var RecurrentPaymentsRepository */
    private $recurrentPaymentsRepository;

    /** @var ApplicationConfig */
    private $applicationConfig;

    public function __construct(
        PaymentsRepository $paymentsRepository,
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        ApplicationConfig $applicationConfig
    ) {
        $this->paymentsRepository = $paymentsRepository;
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->applicationConfig = $applicationConfig;
    }

    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload();
        $this->logger->info('Creating recurring payment', ['payment' => $payload['id']]);

        $payment = $this->paymentsRepository->find($payload['id']);

        if ($payment->status != 'paid') {
            $this->logger->warning('Payment is not paid', ['payment' => $payment->id]);
            return false;
        }

        $recurrent_payment = $this->recurrentPaymentsRepository->recurrent($payment);
        if ($recurrent_payment !== false) {
            $this->logger->warning('Recurrent payment already exists', ['payment' => $payment->id]);
            return false;
        }

        $retries = explode(', ', $this->applicationConfig->get('recurrent_payment_charges'));
        $retries = count((array)$retries);

        $row = $this->recurrentPaymentsRepository->add(
            $payload['token'],
            $payment,
            $this->recurrentPaymentsRepository->calculateChargeAt($payment),
            null,
            --$retries
        );
        return $row instanceof IRow;
    }
}
