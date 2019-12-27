<?php

namespace Crm\PaymentsModule\Commands;

use Crm\PaymentsModule\GatewayFactory;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;

class RecurrentPaymentsCardCheck extends Command
{
    private $recurrentPaymentsRepository;

    private $gatewayFactory;

    public function __construct(RecurrentPaymentsRepository $recurrentPaymentsRepository, GatewayFactory $gatewayFactory)
    {
        parent::__construct();
        $this->gatewayFactory = $gatewayFactory;
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
    }

    protected function configure()
    {
        $this->setName('payments:check_cards')
            ->setDescription('Check cards that would be charged next month');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $start = microtime(true);

        $output->writeln('');
        $output->writeln('<info>***** Recurrent Payment *****</info>');
        $output->writeln('');

        $chargeableRecurrentPayments = $this->recurrentPaymentsRepository->getChargeablePayments();

        $output->writeln('We will check ' . $chargeableRecurrentPayments->count('*') . 'cards');

        foreach ($chargeableRecurrentPayments as $recurrentPayment) {
            $gateway = $this->gatewayFactory->getGateway($recurrentPayment->payment_gateway->code);

            try {
                $response = $gateway->checkValid($recurrentPayment->cid);
                $output->writeln('<info>Card_id: ' . $recurrentPayment->cid . ' User_id: ' . $recurrentPayment->user_id . ' Response: ' . ($response ? 'TRUE' : '<error>FALSE</error>') . '</info>');
            } catch (\Exception $e) {
                Debugger::log($e->getMessage());
            }
        }

        $end = microtime(true);
        $duration = $end - $start;

        $output->writeln('');
        $output->writeln('<info>All done. Took ' . round($duration, 2) . ' sec.</info>');
        $output->writeln('');

        return 0;
    }
}
