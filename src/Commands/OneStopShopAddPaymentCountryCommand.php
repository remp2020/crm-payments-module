<?php

namespace Crm\PaymentsModule\Commands;

use Crm\ApplicationModule\Commands\DecoratedCommandTrait;
use Crm\PaymentsModule\Models\GeoIp\GeoIpException;
use Crm\PaymentsModule\Models\OneStopShop\OneStopShop;
use Crm\PaymentsModule\Models\OneStopShop\OneStopShopCountryConflictException;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Models\RecurrentPaymentsResolver;
use Crm\PaymentsModule\Repositories\PaymentItemsRepository;
use Crm\PaymentsModule\Repositories\PaymentMetaRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\UsersModule\Repositories\CountriesRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class OneStopShopAddPaymentCountryCommand extends Command
{
    use DecoratedCommandTrait;
    public function __construct(
        private RecurrentPaymentsRepository $recurrentPaymentsRepository,
        private PaymentsRepository $paymentsRepository,
        private PaymentMetaRepository $paymentMetaRepository,
        private PaymentItemsRepository $paymentItemsRepository,
        private CountriesRepository $countriesRepository,
        private OneStopShop $oneStopShop,
        private RecurrentPaymentsResolver $recurrentPaymentsResolver,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('payments:resolve_payment_country')
            ->addOption(
                'payment_ids',
                null,
                InputOption::VALUE_REQUIRED,
                "IDs of records from 'payments' table. Expects list of values separated by comma."
            )
            ->addOption(
                'from',
                null,
                InputOption::VALUE_REQUIRED,
                "Include only payments created after the provided date."
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->oneStopShop->isEnabled()) {
            $this->line('One Stop Shop is not enabled, exiting');
            return self::SUCCESS;
        }

        $start = microtime(true);

        if ($paymentIdsOption = $input->getOption('payment_ids')) {
            $paymentIds = explode(',', $paymentIdsOption);
            $paymentsToProcess = $this->getPaymentsWithMissingPaymentCountry()
                ->where('payments.id IN (?)', $paymentIds);
        } else {
            $paymentsToProcess = $this->getPaymentsWithMissingPaymentCountry();
        }

        if ($fromOption = $input->getOption('from')) {
            $from = DateTime::from($fromOption);
            $paymentsToProcess = $paymentsToProcess->where('payments.created_at >= ?', $from);
        }

        $this->line('Processing: <info>' . $paymentsToProcess->count('*') . '</info> payments');
        $this->line('');

        $lastPaymentId = 0;
        $step = 1000;

        while (true) {
            $payments = (clone $paymentsToProcess)
                ->where('payments.id > ?', $lastPaymentId)
                ->limit($step)
                ->fetchAll();

            if (count($payments) === 0) {
                break;
            }

            foreach ($payments as $payment) {
                $lastPaymentId = $payment->id;
                try {
                    if ($payment->recurrent_charge) {
                        $recurrentPayment = $this->recurrentPaymentsRepository->findByPayment($payment);
                        $paymentData = $this->recurrentPaymentsResolver->resolvePaymentData($recurrentPayment);

                        if (!$paymentData->paymentCountry) {
                            $this->error(" * Payment [{$payment->id}] - unable to add country to payment, no resolution found");
                            continue;
                        }

                        $country = $paymentData->paymentCountry;
                    } else {
                        $countryResolution = $this->oneStopShop->resolveCountry(
                            user: $payment->user,
                            paymentAddress: $payment->address,
                            // create virtual container containing ONLY subscription types payment items, important for OneStopShop country resolver
                            paymentItemContainer: $this->paymentItemContainerFromPayment($payment),
                            ipAddress: $payment->ip,
                        );

                        if (!$countryResolution) {
                            $this->error(" * Payment [{$payment->id}] - unable to add country to payment, no resolution found");
                            continue;
                        }

                        $country = $this->countriesRepository->findByIsoCode($countryResolution->countryCode);
                    }

                    $this->paymentsRepository->update($payment, [
                        'payment_country_id' => $country->id,
                    ]);

                    $this->line(" * Payment <info>[{$payment->id}]</info> resolved to <info>[" . $country->iso_code . "]</info>");

                    $this->resolveRecurrentChildren($payment, $country);
                } catch (OneStopShopCountryConflictException|GeoIpException $e) {
                    $this->error(" * Payment [{$payment->id}] - unable to add payment country: " . $e->getMessage());
                    continue;
                }
            }
        }

        $end = microtime(true);
        $duration = $end - $start;

        $this->line('');
        $this->info('All done. Took ' . round($duration, 2) . ' sec.');
        $this->line('');

        return Command::SUCCESS;
    }

    private function getPaymentsWithMissingPaymentCountry(): Selection
    {
        return $this->paymentsRepository->getTable()
            ->where([
                'status' => [PaymentsRepository::STATUS_PAID, PaymentsRepository::STATUS_PREPAID],
                'payment_country_id IS NULL',
                'user.active = ?' => 1,
            ])
            ->order('payments.id ASC');
    }

    private function resolveRecurrentChildren(ActiveRow $payment, ActiveRow $country)
    {
        $recurrentPayment = $this->recurrentPaymentsRepository->recurrent($payment);
        if (!$recurrentPayment || !$recurrentPayment->payment) {
            return;
        }

        $nextPayment = $recurrentPayment->payment;
        if ($nextPayment->payment_country_id) {
            return;
        }

        $this->paymentsRepository->update($nextPayment, [
            'payment_country_id' => $country->id,
        ]);
        $this->line("   * subsequent payment <info>[{$nextPayment->id}]</info> resolved to <info>[" . $country->iso_code . "]</info>");

        $this->resolveRecurrentChildren($nextPayment, $country);
    }

    private function paymentItemContainerFromPayment(ActiveRow $payment): PaymentItemContainer
    {
        $container = new PaymentItemContainer();
        foreach ($this->paymentItemsRepository->getByPayment($payment) as $item) {
            if ($item->type === SubscriptionTypePaymentItem::TYPE) {
                $container->addItem(SubscriptionTypePaymentItem::fromPaymentItem($item));
            }
        }
        return $container;
    }
}
