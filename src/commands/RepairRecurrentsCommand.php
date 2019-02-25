<?php

namespace Crm\PaymentsModule\Commands;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use League\Event\Emitter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RepairRecurrentsCommand extends Command
{
    private $recurrentPaymentsRepository;

    private $paymentsRepository;

    private $emitter;

    private $applicationConfig;

    public function __construct(
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        PaymentsRepository $paymentsRepository,
        Emitter $emitter,
        ApplicationConfig $applicationConfig
    ) {
        parent::__construct();
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->paymentsRepository = $paymentsRepository;
        $this->emitter = $emitter;
        $this->applicationConfig = $applicationConfig;
    }

    protected function configure()
    {
        $this->setName('payments:repair_recurrents')
            ->setDescription('Repair broken recurrents from one incident')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('');
        $output->writeln('<info>***** Repairing recurrents *****</info>');
        $output->writeln('');

        $retries = explode(', ', $this->applicationConfig->get('recurrent_payment_charges'));
        $retries = count((array)$retries);
        $retries--;

        $ids = [
            239041,239040,239046,239048,239047,239052,239051,239050,239053,239054,239057,239056,239060,239064,239063,239065,239069,239071,239066,239074,239075,239078,239077,239083,239087,239085,224087,239089,239090,239092,239106,239109,239112,239114,239115,239116,239117,239119,239124,239125,
        ];

        foreach ($ids as $id) {
            echo "ID $id\n";
            $payment = $this->paymentsRepository->find($id);
            if (!$payment) {
                continue;
            }
            $recurrentPayment = $this->recurrentPaymentsRepository->recurrent($payment);
            if (!$recurrentPayment) {
                echo "Recurrent broken\n";
                continue;
            }
//            var_dump($recurrentPayment);

            $newPayment = $this->paymentsRepository->all()->where(['user_id' => $payment->user_id, 'status' => 'paid', 'paid_at > ?' => $payment->paid_at])->limit(1)->fetch();

            if (!$newPayment) {
                echo "BROKEN\n";
                continue;
            }

            var_dump($newPayment->id);
//            die();

            $this->recurrentPaymentsRepository->add(
                $recurrentPayment->cid,
                $newPayment,
                $newPayment->subscription->end_time,
                $recurrentPayment->custom_amount,
                $retries
            );

            $this->recurrentPaymentsRepository->update($recurrentPayment, [
                'status' => '00',
                'state' => RecurrentPaymentsRepository::STATE_CHARGED,
                'payment_id' => $newPayment->id,
            ]);

            var_dump($recurrentPayment->id);

//            $this->emitter->emit(new RecurrentPaymentRenewedEvent($recurrentPayment));

            var_dump($newPayment->id);

            echo "---\n";
        }

        $output->writeln('');
        $output->writeln('<info>All done.</info>');
        $output->writeln('');
    }
}
