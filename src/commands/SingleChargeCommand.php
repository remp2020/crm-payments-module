<?php

namespace Crm\PaymentsModule\Commands;

use Crm\PaymentsModule\GatewayFactory;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\SubscriptionsModule\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SingleChargeCommand extends Command
{
    private $recurrentPaymentsRepository;

    private $gatewayFactory;

    private $paymentsRepository;

    private $subscriptionTypesRepository;

    public function __construct(
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        GatewayFactory $gatewayFactory,
        PaymentsRepository $paymentsRepository,
        SubscriptionTypesRepository $subscriptionTypesRepository
    ) {
        parent::__construct();
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->gatewayFactory = $gatewayFactory;
        $this->paymentsRepository = $paymentsRepository;
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
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
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $cid = $input->getOption('cid');
        if (!$cid) {
            $output->writeln("<error>ERROR</error>: recurrent payment with cid <info>{$cid}</info> doesn't exist.");
            return;
        }
        $amount = filter_var($input->getOption('amount'), FILTER_VALIDATE_FLOAT);
        if ($amount <= 0) {
            $output->writeln("<error>ERROR</error>: specified amount <info>{$amount}</info> has to be greater than zero.");
            return;
        }
        $description = $input->getOption('description');
        if (!$description) {
            $output->writeln("<error>ERROR</error>: description has to be entered.");
            return;
        }
        $subscriptionTypeCode = $input->getOption('subscription_type_code');
        if (!$subscriptionTypeCode) {
            $output->writeln("<error>ERROR</error>: subscription type code has to be entered.");
            return;
        }
        $subscriptionType = $this->subscriptionTypesRepository->findByCode($subscriptionTypeCode);
        if (!$subscriptionType) {
            $output->writeln("<error>ERROR</error>: subscription type with code <info>{$subscriptionTypeCode}</info> doesn't exist.");
            return;
        }

        $recurrentPayment = $this->recurrentPaymentsRepository->getTable()
            ->where(['cid' => $cid])
            ->order('created_at DESC')
            ->limit(1)
            ->fetch();

        $paymentItemContainer = new PaymentItemContainer();
        $containerItems = SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType);
        if (count($containerItems) === 1) {
            $containerItems[0]->forcePrice($amount);
            $containerItems[0]->forceName($description);
            $paymentItemContainer->addItems($containerItems);
        } else {
            $output->writeln("<error>ERROR</error>: unable to determine VAT, provided subscription type has multiple payment items; please provide subscription type with one item");
            return;
        }

        $payment = $this->paymentsRepository->add(
            $subscriptionType,
            $recurrentPayment->payment_gateway,
            $recurrentPayment->user,
            $paymentItemContainer,
            null,
            $amount,
            new \DateTime(),
            new \DateTime('+1 minute'),
            $description,
            null,
            null,
            null,
            null,
            true
        );

        $gateway = $this->gatewayFactory->getGateway($recurrentPayment->payment_gateway->code);
        $gateway->charge($payment, $recurrentPayment->cid);
        $this->paymentsRepository->updateStatus($payment, PaymentsRepository::STATUS_PAID);
    }
}
