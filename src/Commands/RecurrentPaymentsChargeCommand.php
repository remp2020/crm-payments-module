<?php

namespace Crm\PaymentsModule\Commands;

use Crm\ApplicationModule\Commands\DecoratedCommandTrait;
use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\PaymentsModule\Events\BeforeRecurrentPaymentChargeEvent;
use Crm\PaymentsModule\Models\GatewayFactory;
use Crm\PaymentsModule\Models\GatewayFail;
use Crm\PaymentsModule\Models\Gateways\ExternallyChargedRecurrentPaymentInterface;
use Crm\PaymentsModule\Models\Gateways\GatewayAbstract;
use Crm\PaymentsModule\Models\Gateways\RecurrentPaymentInterface;
use Crm\PaymentsModule\Models\RecurrentPaymentFailStop;
use Crm\PaymentsModule\Models\RecurrentPaymentFailTry;
use Crm\PaymentsModule\Models\RecurrentPaymentFastCharge;
use Crm\PaymentsModule\Models\RecurrentPaymentsProcessor;
use Crm\PaymentsModule\Models\RecurrentPaymentsResolver;
use Crm\PaymentsModule\Repositories\PaymentLogsRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use League\Event\Emitter;
use Nette\Localization\Translator;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;

class RecurrentPaymentsChargeCommand extends Command
{
    use DecoratedCommandTrait;
    private int $fastChargeThreshold = 0;

    public function __construct(
        private RecurrentPaymentsRepository $recurrentPaymentsRepository,
        private PaymentsRepository $paymentsRepository,
        private GatewayFactory $gatewayFactory,
        private PaymentLogsRepository $paymentLogsRepository,
        private Emitter $emitter,
        private ApplicationConfig $applicationConfig,
        private RecurrentPaymentsResolver $recurrentPaymentsResolver,
        private RecurrentPaymentsProcessor $recurrentPaymentsProcessor,
        private Translator $translator
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('payments:charge')
            ->setDescription("Charges recurrent payments ready to be charged. It's highly recommended to use flock or similar tool to prevent multiple instances of this command running.")
            ->addOption(
                'recurrent_payment_ids',
                null,
                InputOption::VALUE_REQUIRED,
                "IDs of records from 'recurrent_payments' table. Expects list of values separated by comma."
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $start = microtime(true);

        if ($input->getOption('recurrent_payment_ids')) {
            $recurrentPaymentIdsOption = $input->getOption('recurrent_payment_ids');
            $recurrentPaymentIds = explode(',', $recurrentPaymentIdsOption);
            $chargeableRecurrentPayments = $this->recurrentPaymentsRepository->getChargeablePayments()
                ->where('id IN (?)', $recurrentPaymentIds);
        } else {
            $chargeableRecurrentPayments = $this->recurrentPaymentsRepository->getChargeablePayments();
        }

        $this->line('Charging: <info>' . $chargeableRecurrentPayments->count('*') . '</info> payments');
        $this->line('');

        foreach ($chargeableRecurrentPayments as $recurrentPayment) {
            try {
                $this->validateRecurrentPayment($recurrentPayment);
            } catch (RecurrentPaymentFastCharge $e) {
                Debugger::log($e->getMessage(), Debugger::EXCEPTION);
                $this->error($e->getMessage());
                continue;
            }

            if (!isset($recurrentPayment->payment_id)) {
                $paymentData = $this->recurrentPaymentsResolver->resolvePaymentData($recurrentPayment);

                $payment = $this->paymentsRepository->add(
                    subscriptionType: $paymentData->subscriptionType,
                    paymentGateway: $recurrentPayment->payment_gateway,
                    user: $recurrentPayment->user,
                    paymentItemContainer: $paymentData->paymentItemContainer,
                    amount: $paymentData->customChargeAmount,
                    additionalAmount: $paymentData->additionalAmount,
                    additionalType: $paymentData->additionalType,
                    address: $paymentData->address,
                    recurrentCharge: true
                );

                $this->recurrentPaymentsRepository->update($recurrentPayment, [
                    'payment_id' => $payment->id,
                ]);
            }

            $this->chargeRecurrentPayment($recurrentPayment);
        }

        $end = microtime(true);
        $duration = $end - $start;

        $this->line('');
        $this->line('EndDate: ' . (new DateTime())->format(DATE_RFC3339));
        $this->info('All done. Took ' . round($duration, 2) . ' sec.');
        $this->line('');

        return Command::SUCCESS;
    }

    private function chargeRecurrentPayment($recurrentPayment): void
    {
        $customChargeAmount = $this->recurrentPaymentsResolver->resolveCustomChargeAmount($recurrentPayment);

        // ability to modify payment
        $this->emitter->emit(new BeforeRecurrentPaymentChargeEvent($recurrentPayment->payment, $recurrentPayment->cid));
        $payment = $this->paymentsRepository->find($recurrentPayment->payment_id); // reload

        if (!$payment) {
            throw new \RuntimeException("Error loading payment with ID=[$recurrentPayment->payment_id]");
        }

        /** @var RecurrentPaymentInterface $gateway */
        $gateway = $this->gatewayFactory->getGateway($payment->payment_gateway->code);
        if (!$gateway instanceof GatewayAbstract) {
            throw new \Exception('In order to use chargeRecurrentPayment, the gateway needs to implement GatewayAbstract: ' . get_class($gateway));
        }

        try {
            if ($payment->status === PaymentsRepository::STATUS_PAID) {
                $this->recurrentPaymentsProcessor->processChargedRecurrent(
                    $recurrentPayment,
                    $payment->status,
                    $gateway->getResultCode(),
                    $gateway->getResultMessage(),
                    $customChargeAmount
                );
            } else {
                $result = $gateway->charge($payment, $recurrentPayment->cid);
                switch ($result) {
                    case RecurrentPaymentInterface::CHARGE_OK:
                        $paymentStatus = PaymentsRepository::STATUS_PAID;
                        $chargeAt = null;
                        if ($gateway instanceof ExternallyChargedRecurrentPaymentInterface) {
                            $paymentStatus = $gateway->getChargedPaymentStatus();
                            $chargeAt = $gateway->getSubscriptionExpiration($recurrentPayment->cid);
                        }
                        $this->recurrentPaymentsProcessor->processChargedRecurrent(
                            $recurrentPayment,
                            $paymentStatus,
                            $gateway->getResultCode(),
                            $gateway->getResultMessage(),
                            $customChargeAmount,
                            $chargeAt
                        );
                        break;
                    case RecurrentPaymentInterface::CHARGE_PENDING:
                        $this->recurrentPaymentsProcessor->processPendingRecurrent($recurrentPayment);
                        break;
                    default:
                        throw new \Exception('unhandled charge result provided by gateway: ' . $result);
                }
            }
        } catch (RecurrentPaymentFailTry $exception) {
            $this->recurrentPaymentsProcessor->processFailedRecurrent(
                $recurrentPayment,
                $gateway->getResultCode(),
                $gateway->getResultMessage(),
                $customChargeAmount
            );
        } catch (RecurrentPaymentFailStop $exception) {
            $this->recurrentPaymentsProcessor->processStoppedRecurrent(
                $recurrentPayment,
                $gateway->getResultCode(),
                $gateway->getResultMessage()
            );
        } catch (GatewayFail $exception) {
            $this->recurrentPaymentsProcessor->processRecurrentChargeError(
                $recurrentPayment,
                $exception->getCode(),
                $exception->getMessage(),
                $customChargeAmount
            );
        }

        $this->paymentLogsRepository->add(
            $gateway->isSuccessful() ? 'OK' : 'ERROR',
            Json::encode($gateway->getResponseData()),
            'recurring-payment-automatic-charge',
            $payment->id
        );

        $now = new DateTime();
        $this->line("[{$now->format(DATE_RFC3339)}] Recurrent payment: #{$recurrentPayment->id} (<comment>cid {$recurrentPayment->cid}</comment>)");
        $this->line("  * status: <info>{$gateway->getResultCode()}</info>");
        $this->line("  * message: {$gateway->getResultMessage()}");
    }

    private function validateRecurrentPayment($recurrentPayment): void
    {
        $parentRecurrentPayment = $this->recurrentPaymentsRepository->getLastWithState($recurrentPayment, RecurrentPaymentsRepository::STATE_CHARGED);
        if (!$parentRecurrentPayment) {
            return;
        }

        // if threshold is set to 0 - fast charge check is disabled
        if (!$this->fastChargeThreshold) {
            return;
        }

        $diffHours = ((new DateTime())->getTimestamp() - $parentRecurrentPayment->charge_at->getTimestamp()) / 3600;

        if ($diffHours < $this->fastChargeThreshold || $parentRecurrentPayment->charge_at === $recurrentPayment->charge_at) {
            $this->recurrentPaymentsRepository->update($recurrentPayment, [
                'state' => RecurrentPaymentsRepository::STATE_SYSTEM_STOP,
                'note' => 'Fast charge',
            ]);

            throw new RecurrentPaymentFastCharge($recurrentPayment);
        }
    }

    public function setFastChargeThreshold(int $threshold): void
    {
        $this->fastChargeThreshold = $threshold;
    }
}
