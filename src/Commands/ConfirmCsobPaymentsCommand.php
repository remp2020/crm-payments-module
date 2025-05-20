<?php

namespace Crm\PaymentsModule\Commands;

use Crm\PaymentsModule\Models\Gateways\Csob;
use Crm\PaymentsModule\Models\Gateways\CsobOneClick;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\PaymentProcessor;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ConfirmCsobPaymentsCommand extends Command
{
    public function __construct(
        private readonly PaymentsRepository $paymentsRepository,
        private readonly PaymentProcessor $paymentProcessor,
    ) {
        parent::__construct();
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
                'pay_id',
            )
            ->where([
                'payment_gateway.code' => [Csob::GATEWAY_CODE, CsobOneClick::GATEWAY_CODE],
                'recurrent_charge' => false,
                ':payment_meta.id IS NOT NULL',
            ]);

        foreach ($unconfirmedPayments as $unconfirmedPayment) {
            $output->writeln("Processing {$unconfirmedPayment->variable_symbol}");

            // try to complete payment, without automatically updating status
            // prevents payment status to be updated to `fail` (payment should be usable in notifications) respekt#150
            $this->paymentProcessor->complete(
                payment: $unconfirmedPayment,
                callback: function ($payment, $gateway, $status) {
                    if ($payment->status !== $status && $status !== PaymentStatusEnum::Fail->value) {
                        $this->paymentsRepository->updateStatus($payment, $status, true);
                        $payment = $this->paymentsRepository->find($payment->id);
                        $this->paymentProcessor->createRecurrentPayment($payment, $gateway);
                    }
                },
                preventPaymentStatusUpdate: true,
            );
        }

        return Command::SUCCESS;
    }
}
