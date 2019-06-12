<?php

namespace Crm\PaymentsModule\Commands;

use Crm\PaymentsModule\GatewayFactory;
use Crm\PaymentsModule\Gateways\RecurrentPaymentInterface;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateRecurrentPaymentsExpiresCommand extends Command
{
    private $recurrentPaymentsRepository;

    private $gatewayFactory;

    public function __construct(
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        GatewayFactory $gatewayFactory
    ) {
        parent::__construct();
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->gatewayFactory = $gatewayFactory;
    }

    protected function configure()
    {
        $this->setName('payments:update_recurrent_payments_expires')
            ->setDescription('Update recurrent payments expires')
            ->addOption('count', null, InputOption::VALUE_REQUIRED, 'Number of cids to process');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $start = microtime(true);

        $output->writeln('');
        $output->writeln('<info>***** Recurrent Payment *****</info>');
        $output->writeln('');

        $count = 100;
        if ($input->getOption('count')) {
            $count = intval($input->getOption('count'));
        }

        $recurrentPayments = $this->recurrentPaymentsRepository->all()->where(['expires_at' => null])->limit($count);

        $gateways = [];
        foreach ($recurrentPayments as $recurrentPayment) {
            $gateways[$recurrentPayment->payment_gateway->code][$recurrentPayment->cid] = $recurrentPayment;
        }

        if (empty($gateways)) {
            $output->writeln('<info>No cards.</info>');
        }

        foreach ($gateways as $code => $recurrentPayments) {
            $gateway = $this->gatewayFactory->getGateway($code);
            if (!$gateway instanceof RecurrentPaymentInterface) {
                $output->writeln("<error>Error: gateway {$code} does not implement RecurrentPaymentInterface</error>");
            }

            try {
                $result = $gateway->checkExpire(array_keys($recurrentPayments));
                foreach ($result as $token => $expire) {
                    $recurrentPayment = $recurrentPayments[$token];
                    $this->recurrentPaymentsRepository->update($recurrentPayment, [
                        'expires_at' => $expire,
                    ]);
                    $output->writeln('<info>Card_id: ' . $recurrentPayment->cid . ' User_id: ' . $recurrentPayment->user_id . ' Expires_at: ' . $expire->format('Y-m-d H:i:s') . '</info>');
                }
            } catch (\Exception $e) {
                $output->writeln("<error>Error {$e->getMessage()}</error>");
            }
        }

        $end = microtime(true);
        $duration = $end - $start;

        $output->writeln('');
        $output->writeln('<info>All done. Took ' . round($duration, 2) . ' sec.</info>');
        $output->writeln('');
    }
}
