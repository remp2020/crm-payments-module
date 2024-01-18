<?php

namespace Crm\PaymentsModule\Commands;

use Crm\PaymentsModule\Events\BeforeRecurrentPaymentChargeEvent;
use Crm\PaymentsModule\Models\GatewayFactory;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use League\Event\Emitter;
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

    private Emitter $emitter;

    public function __construct(
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        GatewayFactory $gatewayFactory,
        PaymentsRepository $paymentsRepository,
        SubscriptionTypesRepository $subscriptionTypesRepository,
        Emitter $emitter
    ) {
        parent::__construct();
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->gatewayFactory = $gatewayFactory;
        $this->paymentsRepository = $paymentsRepository;
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
        $this->emitter = $emitter;
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

        $this->emitter->emit(new BeforeRecurrentPaymentChargeEvent($payment, $recurrentPayment->cid)); // ability to modify payment
        $payment = $this->paymentsRepository->find($payment->id); // reload

        $gateway = $this->gatewayFactory->getGateway($payment->payment_gateway->code);
        $gateway->charge($payment, $recurrentPayment->cid);
        $this->paymentsRepository->updateStatus($payment, PaymentsRepository::STATUS_PAID);

        return Command::SUCCESS;
    }
}
