<?php

namespace Crm\PaymentsModule\Commands;

use Crm\ApplicationModule\Commands\DecoratedCommandTrait;
use Crm\PaymentsModule\Models\GatewayFactory;
use Crm\PaymentsModule\Models\Gateways\RecurrentPaymentInterface;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Nette\Utils\DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateRecurrentPaymentsExpiresCommand extends Command
{
    use DecoratedCommandTrait;

    public function __construct(
        private readonly RecurrentPaymentsRepository $recurrentPaymentsRepository,
        private readonly GatewayFactory $gatewayFactory,
        private readonly PaymentGatewaysRepository $paymentGatewaysRepository
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('payments:update_recurrent_payments_expires')
            ->setDescription('Update recurrent payments expires')
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of recurrent payments to process'
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
            ->select('payment_gateway.code, payment_method_id')
            ->where([
                'state' => RecurrentPaymentsRepository::STATE_ACTIVE,
            ])
            ->group('payment_gateway.code, payment_method.external_token');

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
                return Command::FAILURE;
            }
            $recurrentPayments->where(['recurrent_payments.payment_gateway_id' => $gateway->id]);
        }

        $totalCount = (clone $recurrentPayments)->count('*');
        if ($limit) {
            $recurrentPayments->limit($limit);
        }

        $gateways = [];
        foreach ($recurrentPayments as $recurrentPayment) {
            $externalToken = $recurrentPayment->payment_method->external_token;
            $gateways[$recurrentPayment->code][$externalToken] = $externalToken;
        }
        if (empty($gateways)) {
            $output->writeln('<info>No cards.</info>');
        }

        $output->writeln("Processing <comment>{$recurrentPayments->count()}</comment>/<info>{$totalCount}</info> recurrent payments");

        foreach ($gateways as $code => $externalTokens) {
            /** @var RecurrentPaymentInterface $gateway */
            $gateway = $this->gatewayFactory->getGateway($code);
            if (!$gateway instanceof RecurrentPaymentInterface) {
                $output->writeln("<error>Error: gateway {$code} does not implement RecurrentPaymentInterface</error>");
            }
            $paymentGateway = $this->paymentGatewaysRepository->findByCode($code);

            try {
                foreach (array_chunk($externalTokens, 200) as $chunk) {
                    $result = $gateway->checkExpire($chunk);
                    foreach ($result as $externalToken => $expire) {
                        $filteredRecurrentPayments = $this->recurrentPaymentsRepository->getTable()
                            ->where(['payment_method.external_token' => (string) $externalToken]);

                        $previousExpiration = null;
                        $updated = false;

                        foreach ($filteredRecurrentPayments as $filteredRecurrentPayment) {
                            if ($filteredRecurrentPayment->expires_at == $expire) {
                                continue;
                            }
                            if (!$previousExpiration && $filteredRecurrentPayment->expires_at) {
                                $previousExpiration = $filteredRecurrentPayment->expires_at;
                            }
                            $this->recurrentPaymentsRepository->update($filteredRecurrentPayment, [
                                'expires_at' => $expire
                            ]);
                            $updated = true;
                        }

                        if ($updated) {
                            $output->writeln(sprintf(
                                '  * %s EXTERNAL_TOKEN <comment>%s</comment> expires at %s %s',
                                $paymentGateway->name,
                                $externalToken,
                                $expire->format('Y-m-d'),
                                $previousExpiration ? "(previously {$previousExpiration->format('Y-m-d')})" : ''
                            ));
                        } else {
                            $output->writeln(sprintf(
                                '  * %s EXTERNAL_TOKEN <comment>%s</comment> skipped, no change in expiration',
                                $paymentGateway->name,
                                $externalToken,
                            ));
                        }
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

        return Command::SUCCESS;
    }
}
