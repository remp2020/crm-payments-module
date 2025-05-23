<?php

namespace Crm\PaymentsModule\Commands;

use Crm\PaymentsModule\Events\RecurrentPaymentCardExpiredEvent;
use Crm\PaymentsModule\Models\RecurrentPayment\RecurrentPaymentStateEnum;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use League\Event\Emitter;
use Nette\Utils\DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StopRecurrentPaymentsExpiresCommand extends Command
{
    private $recurrentPaymentsRepository;

    private $emitter;

    public function __construct(
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        Emitter $emitter,
    ) {
        parent::__construct();
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->emitter = $emitter;
    }

    protected function configure()
    {
        $this->setName('payments:stop_expired_recurrent_payments')
            ->setDescription('Stop expired recurrent payments')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $start = microtime(true);

        $output->writeln('');
        $output->writeln('<info>***** Stopping Expired Recurrent Payments *****</info>');
        $output->writeln('');

        $date = DateTime::from('first day of this month 00:00:00');
        $nextMonth = DateTime::from('last day of this month 23:59:59');

        $output->writeln("Stopping recurrent payments expired before <info>{$date->format('c')}</info> supposed to be charged between <info>{$date->format('c')}</info> - <info>{$nextMonth->format('c')}</info>");

        $recurrentPayments = $this->recurrentPaymentsRepository->all()->where([
            'expires_at <= ?' => $date,
            'state' => RecurrentPaymentStateEnum::Active->value,
            'charge_at >= ?' => $date,
            'charge_at <= ?' => $nextMonth,
        ]);

        $output->writeln('Total: <info>' . count($recurrentPayments) . '</info>');

        foreach ($recurrentPayments as $recurrentPayment) {
            $output->writeln("Processing user #[{$recurrentPayment->user->id}] <info>{$recurrentPayment->user->public_name}</info>");
            $output->writeln("  * Stopping recurrent <info>{$recurrentPayment->id}</info>");

            $note = 'AutoStop on expired card';
            $this->recurrentPaymentsRepository->update($recurrentPayment, [
                'state' => RecurrentPaymentStateEnum::SystemStop->value,
                'note' => $recurrentPayment->note ? $recurrentPayment->note . ' ' . $note : $note,
            ]);

            $this->emitter->emit(new RecurrentPaymentCardExpiredEvent($recurrentPayment));
        }

        $end = microtime(true);
        $duration = $end - $start;

        $output->writeln('Stopped: <info>' . count($recurrentPayments) . '</info>');

        $output->writeln('');
        $output->writeln('<info>All done. Took ' . round($duration, 2) . ' sec.</info>');
        $output->writeln('');

        return Command::SUCCESS;
    }
}
