<?php

namespace Crm\PaymentsModule\Commands;

use Crm\ApplicationModule\Commands\DecoratedCommandTrait;
use Crm\PaymentsModule\GatewayFactory;
use Crm\PaymentsModule\Gateways\RecurrentPaymentInterface;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Nette\Utils\DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateRecurrentPaymentsExpiresCommand extends Command
{
    use DecoratedCommandTrait;

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
            )
            ->addOption(
                'all',
                null,
                InputOption::VALUE_NONE,
                'Update all recurrent payments not only without expires_at.'
            )
            ->addOption(
                'charge_before',
                null,
                InputOption::VALUE_REQUIRED,
                'Update only recurrent payments that are in future and before date in this option.'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $start = microtime(true);

        $limit = null;
        if ($input->getOption('limit')) {
            $limit = (int)$input->getOption('limit');
        }

        $recurrentPayments = $this->recurrentPaymentsRepository->all()
            ->select('payment_gateway.code, cid')
            ->where([
                'state' => RecurrentPaymentsRepository::STATE_ACTIVE,
            ])
            ->group('payment_gateway.code, cid');

        if (!$input->getOption('all')) {
            $recurrentPayments->where(['expires_at' => null]);
        }

        if ($input->getOption('charge_before')) {
            $chargeBefore = new DateTime($input->getOption('charge_before'));
            $recurrentPayments->where('charge_at >= NOW()')
                ->where('charge_at < ?', $chargeBefore);
        }

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

        $output->writeln("Processing <comment>{$recurrentPayments->count()}</comment>/<info>{$totalCount}</info> CIDs");

        foreach ($gateways as $code => $cids) {
            $gateway = $this->gatewayFactory->getGateway($code);
            if (!$gateway instanceof RecurrentPaymentInterface) {
                $output->writeln("<error>Error: gateway {$code} does not implement RecurrentPaymentInterface</error>");
            }
            $paymentGateway = $this->paymentGatewaysRepository->findByCode($code);

            try {
                $result = $gateway->checkExpire(array_values($cids));
                foreach ($result as $token => $expire) {
                    $cidRecurrentPayments = $this->recurrentPaymentsRepository->getTable()
                        ->where(['cid' => (string) $token]);

                    $previousExpiration = null;
                    $updated = false;

                    foreach ($cidRecurrentPayments as $cidPayment) {
                        if ($cidPayment->expires_at == $expire) {
                            continue;
                        }
                        if (!$previousExpiration && $cidPayment->expires_at) {
                            $previousExpiration = $cidPayment->expires_at;
                        }
                        $this->recurrentPaymentsRepository->update($cidPayment, [
                            'expires_at' => $expire
                        ]);
                        $updated = true;
                    }

                    if ($updated) {
                        $output->writeln(sprintf(
                            '  * %s CID <comment>%s</comment> expires at %s %s',
                            $paymentGateway->name,
                            $token,
                            $expire->format('Y-m-d'),
                            $previousExpiration ? "(previously {$previousExpiration->format('Y-m-d')})" : ''
                        ));
                    } else {
                        $output->writeln(sprintf(
                            '  * %s CID <comment>%s</comment> skipped, no change in expiration',
                            $paymentGateway->name,
                            $token,
                        ));
                    }
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
