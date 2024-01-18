<?php

namespace Crm\PaymentsModule\Commands;

use Crm\PaymentsModule\Models\Gateways\Csob;
use Crm\PaymentsModule\Models\Gateways\CsobOneClick;
use Crm\PaymentsModule\Models\PaymentProcessor;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ConfirmCsobPaymentsCommand extends Command
{
    private $paymentsRepository;

    private $paymentProcessor;

    public function __construct(
        PaymentsRepository $paymentsRepository,
        PaymentProcessor $paymentProcessor
    ) {
        parent::__construct();
        $this->paymentsRepository = $paymentsRepository;
        $this->paymentProcessor = $paymentProcessor;
    }

    public function configure()
    {
        $this->setName('payments:confirm_csob_payments')
            ->setDescription('Finds all form payments and checks their status via CSOB gateway')
            ->addOption('from', 'f', InputOption::VALUE_OPTIONAL, 'datetime string to specify date range for payments', '-24 hours');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $from = new \DateTime($input->getOption('from'));
        $unconfirmedPayments = $this->paymentsRepository->unconfirmedPayments($from)
            ->joinWhere(
                ':payment_meta',
                "payments.id = :payment_meta.payment_id AND :payment_meta.key = ?",
                'pay_id'
            )
            ->where([
                'payment_gateway.code' => [Csob::GATEWAY_CODE, CsobOneClick::GATEWAY_CODE],
                'recurrent_charge' => false,
                ':payment_meta.id IS NOT NULL',
            ]);

        foreach ($unconfirmedPayments as $unconfirmedPayment) {
            $output->writeln("Processing {$unconfirmedPayment->variable_symbol}");
            $this->paymentProcessor->complete($unconfirmedPayment, function () {
                // no need to do anything...
            });
        }

        return Command::SUCCESS;
    }
}
