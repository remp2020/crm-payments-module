<?php

namespace Crm\PaymentsModule\Commands;

use Crm\PaymentsModule\Repositories\PaymentMethodsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixPaymentMethodRecurrentPaymentInconsistencyCommand extends Command
{
    public function __construct(
        private readonly RecurrentPaymentsRepository $recurrentPaymentsRepository,
        private readonly PaymentMethodsRepository $paymentMethodsRepository,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('payments:fix_payment_method_recurrent_inconsistency')
            ->setDescription('Create payment method and assign to recurrent payment to match user.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('');
        $output->writeln('<info>***** FIX RECURRENTS AND PAYMENT METHODS INCONSISTENCY *****</info>');
        $output->writeln('');

        $recurrentPayments = $this->recurrentPaymentsRepository->getTable()
            ->where('recurrent_payments.user_id != payment_method.user_id');

        foreach ($recurrentPayments as $recurrentPayment) {
            $output->writeln("Recurrent payment ID: <info>{$recurrentPayment->id}</info>");

            // check if payment method already exists
            // use CID because original payment method can already be anonymized
            $paymentMethod = $this->paymentMethodsRepository->findByExternalToken(
                $recurrentPayment->user_id,
                $recurrentPayment->cid
            );

            if (!$paymentMethod) {
                $paymentMethod = $this->paymentMethodsRepository->add(
                    userId: $recurrentPayment->user_id,
                    paymentGatewayId: $recurrentPayment->payment_method->payment_gateway_id,
                    externalToken: $recurrentPayment->cid,
                );

                $output->writeln("  * created new payment method ID <comment>{$paymentMethod->id}</comment> with the same token as recurrent payment CID.");
            } else {
                $output->writeln("  * payment method with the same token as recurrent payment CID is already attached to the user. Use payment method ID <comment>{$paymentMethod->id}</comment>.");
            }

            $this->recurrentPaymentsRepository->update(
                $recurrentPayment,
                [
                    'payment_method_id' => $paymentMethod->id
                ],
            );
        }


        return Command::SUCCESS;
    }
}
