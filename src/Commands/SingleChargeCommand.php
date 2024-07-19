<?php

namespace Crm\PaymentsModule\Commands;

use Crm\PaymentsModule\Events\BeforeRecurrentPaymentChargeEvent;
use Crm\PaymentsModule\Models\GatewayFactory;
use Crm\PaymentsModule\Models\Gateways\RecurrentPaymentInterface;
use Crm\PaymentsModule\Models\GeoIp\GeoIpException;
use Crm\PaymentsModule\Models\OneStopShop\OneStopShop;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use League\Event\Emitter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;

class SingleChargeCommand extends Command
{
    public function __construct(
        private RecurrentPaymentsRepository $recurrentPaymentsRepository,
        private GatewayFactory $gatewayFactory,
        private PaymentsRepository $paymentsRepository,
        private SubscriptionTypesRepository $subscriptionTypesRepository,
        private Emitter $emitter,
        private OneStopShop $oneStopShop,
        private CountriesRepository $countriesRepository,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('payments:single_charge')
            ->setDescription("Charges single recurrent payment (by CID) with specified amount.")
            ->addOption(
                'cid',
                null,
                InputOption::VALUE_REQUIRED,
                'CID to charge'
            )
            ->addOption(
                'amount',
                null,
                InputOption::VALUE_REQUIRED,
                'Amount that the user will be charged'
            )->addOption(
                'description',
                null,
                InputOption::VALUE_REQUIRED,
                'User-readable description what charge includes / why it was charged'
            )->addOption(
                'subscription_type_code',
                null,
                InputOption::VALUE_REQUIRED,
                'Code of subscription type to be used in payment'
            )->addOption(
                'payment_country_code',
                null,
                InputOption::VALUE_REQUIRED,
                'ISO code of payment country for One Stop Shop country resolution. Use if you want to specify payment country manually'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $cid = $input->getOption('cid');
        $recurrentPayment = $this->recurrentPaymentsRepository->getTable()
            ->where(['cid' => (string) $cid])
            ->order('created_at DESC')
            ->limit(1)
            ->fetch();
        if (!$recurrentPayment) {
            $output->writeln("<error>ERROR</error>: recurrent payment with cid <info>{$cid}</info> doesn't exist.");
            return Command::FAILURE;
        }

        $amount = filter_var($input->getOption('amount'), FILTER_VALIDATE_FLOAT);
        if ($amount <= 0) {
            $output->writeln("<error>ERROR</error>: specified amount <info>{$amount}</info> has to be greater than zero.");
            return Command::FAILURE;
        }
        $description = $input->getOption('description');
        if (!$description) {
            $output->writeln("<error>ERROR</error>: description has to be entered.");
            return Command::FAILURE;
        }
        $subscriptionTypeCode = $input->getOption('subscription_type_code');
        if (!$subscriptionTypeCode) {
            $output->writeln("<error>ERROR</error>: subscription type code has to be entered.");
            return Command::FAILURE;
        }
        $subscriptionType = $this->subscriptionTypesRepository->findByCode($subscriptionTypeCode);
        if (!$subscriptionType) {
            $output->writeln("<error>ERROR</error>: subscription type with code <info>{$subscriptionTypeCode}</info> doesn't exist.");
            return Command::FAILURE;
        }
        $paymentCountryCode = $input->getOption('payment_country_code');

        $paymentItemContainer = new PaymentItemContainer();
        $containerItems = SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType);
        if (count($containerItems) === 1) {
            $containerItems[0]->forcePrice($amount);
            $containerItems[0]->forceName($description);
            $paymentItemContainer->addItems($containerItems);
        } else {
            $output->writeln("<error>ERROR</error>: unable to determine VAT, provided subscription type has multiple payment items; please provide subscription type with one item");
            return Command::FAILURE;
        }

        $countryResolution = null;
        try {
            $countryResolution  = $this->oneStopShop->resolveCountry(
                user: $recurrentPayment->user,
                selectedCountryCode: $paymentCountryCode,
                paymentItemContainer: $paymentItemContainer,
            );
        } catch (GeoIpException $exception) {
            // do not crash because of wrong IP resolution, just log
            Debugger::log("SingleChargeCommand OSS GeoIpException: " . $exception->getMessage(), Debugger::ERROR);
        }

        $payment = $this->paymentsRepository->add(
            subscriptionType: $subscriptionType,
            paymentGateway: $recurrentPayment->payment_gateway,
            user: $recurrentPayment->user,
            paymentItemContainer: $paymentItemContainer,
            amount: $amount,
            subscriptionStartAt: new \DateTime(),
            subscriptionEndAt: new \DateTime('+1 minute'),
            note: $description,
            recurrentCharge: true,
            paymentCountry: $countryResolution ? $this->countriesRepository->findByIsoCode($countryResolution->countryCode) : null,
            paymentCountryResolutionReason: $countryResolution?->getReasonValue(),
        );

        $this->emitter->emit(new BeforeRecurrentPaymentChargeEvent($payment, $recurrentPayment->cid)); // ability to modify payment
        $payment = $this->paymentsRepository->find($payment->id); // reload

        /** @var RecurrentPaymentInterface $gateway */
        $gateway = $this->gatewayFactory->getGateway($payment->payment_gateway->code);
        $gateway->charge($payment, $recurrentPayment->cid);
        $this->paymentsRepository->updateStatus($payment, PaymentsRepository::STATUS_PAID);

        return Command::SUCCESS;
    }
}
