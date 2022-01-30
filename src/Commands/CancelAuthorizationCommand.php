<?php

namespace Crm\PaymentsModule\Commands;

use Crm\PaymentsModule\GatewayFactory;
use Crm\PaymentsModule\Gateways\AuthorizationInterface;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;
use Tracy\ILogger;

class CancelAuthorizationCommand extends Command
{
    private $paymentsRepository;

    private $gatewayFactory;

    public function __construct(
        PaymentsRepository $paymentsRepository,
        GatewayFactory $gatewayFactory
    ) {
        parent::__construct();

        $this->paymentsRepository = $paymentsRepository;
        $this->gatewayFactory = $gatewayFactory;
    }

    protected function configure()
    {
        $this->setName('payments:cancel_authorization')
            ->setDescription('Cancel authorization payments');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $authorizationPayments = $this->paymentsRepository->getTable()
            ->where('status', PaymentsRepository::STATUS_AUTHORIZED);

        $output->writeln("We will cancel {$authorizationPayments->count('*')} authorization payments.");

        foreach ($authorizationPayments as $paymentRow) {
            /** @var AuthorizationInterface $gateway */
            $gateway = $this->gatewayFactory->getGateway($paymentRow->payment_gateway->code);
            if (!$gateway instanceof AuthorizationInterface) {
                Debugger::log("Payment gateway doesn't support cancel authorization {$paymentRow->payment_gateway->code}");
                continue;
            }

            try {
                $response = $gateway->cancel($paymentRow);
                $output->writeln("Payment: {$paymentRow->id}, result:" . ($response ? 'success' : 'fail'));

                if ($response) {
                    $this->paymentsRepository->updateStatus($paymentRow, PaymentsRepository::STATUS_REFUND);
                }
            } catch (\Exception $exception) {
                Debugger::log($exception->getMessage(), ILogger::ERROR);
            }
        }

        return Command::SUCCESS;
    }
}
