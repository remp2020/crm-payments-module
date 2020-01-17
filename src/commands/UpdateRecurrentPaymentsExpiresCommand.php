<?php

namespace Crm\PaymentsModule\Commands;

use Crm\PaymentsModule\GatewayFactory;
use Crm\PaymentsModule\Gateways\RecurrentPaymentInterface;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateRecurrentPaymentsExpiresCommand extends Command
{
    private $recurrentPaymentsRepository;

    private $gatewayFactory;

    private $paymentGatewaysRepository;

    public function __construct(
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        GatewayFactory $gatewayFactory,
        PaymentGatewaysRepository $paymentGatewaysRepository
    ) {
        parent::__construct();
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->gatewayFactory = $gatewayFactory;
        $this->paymentGatewaysRepository = $paymentGatewaysRepository;
    }

    protected function configure()
    {
        $this->setName('payments:update_recurrent_payments_expires')
            ->setDescription('Update recurrent payments expires')
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of cids to process'
            )
            ->addOption(
                'gateway',
                null,
                InputOption::VALUE_REQUIRED,
                'Payment gateway code to check use. If not specified, all recurring gateways are checked.'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $start = microtime(true);

        $output->writeln('');
        $output->writeln('<info>***** Recurrent Payment *****</info>');
        $output->writeln('');

        $limit = null;
        if ($input->getOption('limit')) {
            $limit = intval($input->getOption('limit'));
        }

        $recurrentPayments = $this->recurrentPaymentsRepository->all()
            ->select('payment_gateway.code, cid')
            ->where([
                'expires_at' => null,
                'state' => RecurrentPaymentsRepository::STATE_ACTIVE,
            ])
            ->group('payment_gateway.code, cid');

        if ($code = $input->getOption('gateway')) {
            $gateway = $this->paymentGatewaysRepository->findByCode($code);
            if (!$gateway) {
                $output->writeln("<error>ERROR: gateway <info>{$code}</info> doesn't exist:</error>");
                return 1;
            }
            $recurrentPayments->where(['payment_gateway_id' => $gateway->id]);
        }

        $totalCount = (clone $recurrentPayments)->count('*');
        if ($limit) {
            $recurrentPayments->limit($limit);
        }

        $gateways = [];
        foreach ($recurrentPayments as $recurrentPayment) {
            $gateways[$recurrentPayment->code][$recurrentPayment->cid] = $recurrentPayment->cid;
        }
        if (empty($gateways)) {
            $output->writeln('<info>No cards.</info>');
        }

        $output->writeln("Processing <comment>{$recurrentPayments->count()}</comment>/<info>{$totalCount}</info> CIDs without expiration");

        foreach ($gateways as $code => $cids) {
            $gateway = $this->gatewayFactory->getGateway($code);
            if (!$gateway instanceof RecurrentPaymentInterface) {
                $output->writeln("<error>Error: gateway {$code} does not implement RecurrentPaymentInterface</error>");
            }
            $paymentGateway = $this->paymentGatewaysRepository->findByCode($code);

            $output->writeln("Checking <comment>{$paymentGateway->name}</comment> expirations:");

            try {
                $result = $gateway->checkExpire(array_values($cids));
                foreach ($result as $token => $expire) {
                    $cidRecurrentPayments = $this->recurrentPaymentsRepository->getTable()->where(['cid' => $token]);
                    foreach ($cidRecurrentPayments as $cidPayment) {
                        $this->recurrentPaymentsRepository->update($cidPayment, [
                            'expires_at' => $expire
                        ]);
                    }
                    $output->writeln('  * CID: ' . $token . ' (expires at: ' . $expire->format('Y-m-d H:i:s') . ')</info>');
                }
            } catch (\Exception $e) {
                $output->writeln("  * <error>Error {$e->getMessage()}</error>");
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
