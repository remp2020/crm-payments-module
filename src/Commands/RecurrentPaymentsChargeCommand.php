<?php

namespace Crm\PaymentsModule\Commands;

use Crm\ApplicationModule\Commands\DecoratedCommandTrait;
use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\PaymentsModule\Events\BeforeRecurrentPaymentChargeEvent;
use Crm\PaymentsModule\GatewayFactory;
use Crm\PaymentsModule\GatewayFail;
use Crm\PaymentsModule\Gateways\ExternallyChargedRecurrentPaymentInterface;
use Crm\PaymentsModule\Gateways\RecurrentPaymentInterface;
use Crm\PaymentsModule\PaymentItem\DonationPaymentItem;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\RecurrentPaymentFailStop;
use Crm\PaymentsModule\RecurrentPaymentFailTry;
use Crm\PaymentsModule\RecurrentPaymentFastCharge;
use Crm\PaymentsModule\RecurrentPaymentsProcessor;
use Crm\PaymentsModule\RecurrentPaymentsResolver;
use Crm\PaymentsModule\Repository\PaymentLogsRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\SubscriptionsModule\PaymentItem\SubscriptionTypePaymentItem;
use League\Event\Emitter;
use Nette\Localization\Translator;
use Nette\Utils\DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;

class RecurrentPaymentsChargeCommand extends Command
{
    use DecoratedCommandTrait;

    private $recurrentPaymentsRepository;

    private $paymentsRepository;

    private $gatewayFactory;

    private $paymentLogsRepository;

    private $emitter;

    private $applicationConfig;

    private $recurrentPaymentsResolver;

    private $recurrentPaymentsProcessor;

    private $translator;

    public function __construct(
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        PaymentsRepository $paymentsRepository,
        GatewayFactory $gatewayFactory,
        PaymentLogsRepository $paymentLogsRepository,
        Emitter $emitter,
        ApplicationConfig $applicationConfig,
        RecurrentPaymentsResolver $recurrentPaymentsResolver,
        RecurrentPaymentsProcessor $recurrentPaymentsProcessor,
        Translator $translator
    ) {
        parent::__construct();
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->paymentsRepository = $paymentsRepository;
        $this->gatewayFactory = $gatewayFactory;
        $this->paymentLogsRepository = $paymentLogsRepository;
        $this->emitter = $emitter;
        $this->applicationConfig = $applicationConfig;
        $this->recurrentPaymentsResolver = $recurrentPaymentsResolver;
        $this->recurrentPaymentsProcessor = $recurrentPaymentsProcessor;
        $this->translator = $translator;
    }

    protected function configure()
    {
        $this->setName('payments:charge')
            ->setDescription("Charges recurrent payments ready to be charged. It's highly recommended to use flock or similar tool to prevent multiple instances of this command running.")
            ->addArgument(
                'charge',
                null,
                InputOption::VALUE_OPTIONAL,
                'Set to test charge'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $start = microtime(true);

        $chargeableRecurrentPayments = $this->recurrentPaymentsRepository->getChargeablePayments();

        $this->line('Charging: <info>' . $chargeableRecurrentPayments->count('*') . '</info> payments');
        $this->line('');

        foreach ($chargeableRecurrentPayments as $recurrentPayment) {
            try {
                $this->validateRecurrentPayment($recurrentPayment);
            } catch (RecurrentPaymentFastCharge $exception) {
                $msg = 'RecurringPayment_id: ' . $recurrentPayment->id . ' Card_id: ' . $recurrentPayment->cid . ' User_id: ' . $recurrentPayment->user_id . ' Error: Fast charge';
                Debugger::log($msg, Debugger::EXCEPTION);
                $this->error($msg);
                continue;
            }

            $subscriptionType = $this->recurrentPaymentsResolver->resolveSubscriptionType($recurrentPayment);
            $customChargeAmount = $this->recurrentPaymentsResolver->resolveCustomChargeAmount($recurrentPayment);

            if (!isset($recurrentPayment->payment_id) || $recurrentPayment->payment_id === null) {
                $additionalAmount = 0;
                $additionalType = null;
                $parentPayment = $recurrentPayment->parent_payment;
                if ($parentPayment && $parentPayment->additional_type === 'recurrent') {
                    $additionalType = 'recurrent';
                    $additionalAmount = $parentPayment->additional_amount;
                }

                $paymentItemContainer = new PaymentItemContainer();

                // we want to load previous payment items only if new subscription has same subscription type
                // and it isn't upgraded recurrent payment
                if ($subscriptionType->id === $parentPayment->subscription_type_id
                    && $subscriptionType->id === $recurrentPayment->subscription_type_id
                    && $parentPayment->amount === $recurrentPayment->subscription_type->price
                    && !$customChargeAmount
                ) {
                    foreach ($this->paymentsRepository->getPaymentItems($parentPayment) as $key => $item) {
                        // TODO: unset donation payment item without relying on the name of payment item
                        // remove donation from items, it will be added by PaymentsRepository->add().
                        //
                        // Possible solution should be to add `recurrent` field to payment_items
                        // and copy only this items with recurrent flag
                        // for now this should be ok because we are not selling recurring products
                        if ($item['amount'] === $parentPayment->additional_amount &&
                            $item['name'] === $this->translator->translate('payments.admin.donation')
                        ) {
                            continue;
                        }

                        $paymentItemContainer->addItem(new SubscriptionTypePaymentItem(
                            $subscriptionType->id,
                            $item['name'],
                            $item['amount'],
                            $item['vat'],
                            $item['count'],
                            $item['meta']
                        ));

                        // In case of subscription type VAT change, parent payment would copy incorrect VAT rates
                        // into the new payment items. If we see a change in a total price without VAT, we don't
                        // copy the items anymore (the price with VAT was already checked in IF above).

                        $subscriptionTypePaymentItemContainer = new PaymentItemContainer();
                        $subscriptionTypePaymentItemContainer
                            ->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType));

                        if (round($paymentItemContainer->totalPriceWithoutVAT(), 2) !==
                            round($subscriptionTypePaymentItemContainer->totalPriceWithoutVAT(), 2)
                        ) {
                            $paymentItemContainer = $subscriptionTypePaymentItemContainer;
                        }
                    }
                } elseif (!$customChargeAmount) {
                    // if subscription type changed, load the items from new subscription type
                    $paymentItemContainer->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType));
                    // TODO: what about other types of payment items? (e.g. student donation?); seems we're losing them here
                } else {
                    $items = $this->getSubscriptionTypeItemsForCustomChargeAmount($subscriptionType, $customChargeAmount);
                    $paymentItemContainer->addItems($items);
                }

                if ($additionalType == 'recurrent' && $additionalAmount) {
                    $donationPaymentVat = $this->applicationConfig->get('donation_vat_rate');
                    if ($donationPaymentVat === null) {
                        throw new \Exception("Config 'donation_vat_rate' is not set");
                    }
                    $paymentItemContainer->addItem(
                        new DonationPaymentItem(
                            $this->translator->translate('payments.admin.donation'),
                            (float) $additionalAmount,
                            (int) $donationPaymentVat
                        )
                    );
                }

                $payment = $this->paymentsRepository->add(
                    $subscriptionType,
                    $recurrentPayment->payment_gateway,
                    $recurrentPayment->user,
                    $paymentItemContainer,
                    null,
                    $customChargeAmount,
                    null,
                    null,
                    null,
                    $additionalAmount,
                    $additionalType,
                    null,
                    null,
                    true
                );

                $this->recurrentPaymentsRepository->update($recurrentPayment, [
                    'payment_id' => $payment->id,
                ]);
            }

            $this->chargeRecurrentPayment($recurrentPayment, $customChargeAmount);
        }

        $end = microtime(true);
        $duration = $end - $start;

        $this->line('');
        $this->line('EndDate: ' . (new DateTime())->format(DATE_RFC3339));
        $this->info('All done. Took ' . round($duration, 2) . ' sec.');
        $this->line('');

        return Command::SUCCESS;
    }

    private function chargeRecurrentPayment($recurrentPayment, $customChargeAmount)
    {
        $this->emitter->emit(new BeforeRecurrentPaymentChargeEvent($recurrentPayment->payment, $recurrentPayment->cid)); // ability to modify payment
        $payment = $this->paymentsRepository->find($recurrentPayment->payment_id); // reload

        /** @var RecurrentPaymentInterface $gateway */
        $gateway = $this->gatewayFactory->getGateway($payment->payment_gateway->code);
        try {
            if ($payment->status === PaymentsRepository::STATUS_PAID) {
                $this->recurrentPaymentsProcessor->processChargedRecurrent(
                    $recurrentPayment,
                    $payment->status,
                    $gateway->getResultCode(),
                    $gateway->getResultMessage(),
                    $customChargeAmount
                );
            } else {
                $result = $gateway->charge($payment, $recurrentPayment->cid);
                switch ($result) {
                    case RecurrentPaymentInterface::CHARGE_OK:
                        $paymentStatus = PaymentsRepository::STATUS_PAID;
                        $chargeAt = null;
                        if ($gateway instanceof ExternallyChargedRecurrentPaymentInterface) {
                            $paymentStatus = $gateway->getChargedPaymentStatus();
                            $chargeAt = $gateway->getSubscriptionExpiration($recurrentPayment->cid);
                        }
                        $this->recurrentPaymentsProcessor->processChargedRecurrent(
                            $recurrentPayment,
                            $paymentStatus,
                            $gateway->getResultCode(),
                            $gateway->getResultMessage(),
                            $customChargeAmount,
                            $chargeAt
                        );
                        break;
                    case RecurrentPaymentInterface::CHARGE_PENDING:
                        $this->recurrentPaymentsProcessor->processPendingRecurrent($recurrentPayment);
                        break;
                    default:
                        throw new \Exception('unhandled charge result provided by gateway: ' . $result);
                }
            }
        } catch (RecurrentPaymentFailTry $exception) {
            $this->recurrentPaymentsProcessor->processFailedRecurrent(
                $recurrentPayment,
                $gateway->getResultCode(),
                $gateway->getResultMessage(),
                $customChargeAmount
            );
        } catch (RecurrentPaymentFailStop $exception) {
            $this->recurrentPaymentsProcessor->processStoppedRecurrent(
                $recurrentPayment,
                $gateway->getResultCode(),
                $gateway->getResultMessage()
            );
        } catch (GatewayFail $exception) {
            $this->recurrentPaymentsProcessor->processRecurrentChargeError(
                $recurrentPayment,
                $exception->getCode(),
                $exception->getMessage(),
                $customChargeAmount
            );
        }

        $this->paymentLogsRepository->add(
            $gateway->isSuccessful() ? 'OK' : 'ERROR',
            json_encode($gateway->getResponseData()),
            'recurring-payment-automatic-charge',
            $payment->id
        );

        $now = new DateTime();
        $this->line("[{$now->format(DATE_RFC3339)}] Recurrent payment: #{$recurrentPayment->id} (<comment>cid {$recurrentPayment->cid}</comment>)");
        $this->line("  * status: <info>{$gateway->getResultCode()}</info>");
        $this->line("  * message: {$gateway->getResultMessage()}");
    }

    protected function getSubscriptionTypeItemsForCustomChargeAmount($subscriptionType, $customChargeAmount)
    {
        $items = SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType);

        // sort items by vat (higher vat first)
        usort($items, function (SubscriptionTypePaymentItem $a, SubscriptionTypePaymentItem $b) {
            return ($a->vat() <=> $b->vat()) * -1;
        });

        // get vat-amount ratios, floor everything down to avoid scenario that everything is rounded up
        // and sum of items would be greater than charged amount
        $ratios = [];
        foreach ($items as $item) {
            $ratios[$item->vat()] = floor($item->unitPrice() / $subscriptionType->price * 100) / 100;
        }
        // any rounding weirdness (sum of ratios not being 1) should go in favor of higher vat (first item)
        $ratios[array_keys($ratios)[0]] += 1 - array_sum($ratios);

        // update prices based on found ratios
        $sum = 0;
        foreach ($items as $item) {
            $itemPrice = floor($customChargeAmount * $ratios[$item->vat()] * 100) / 100;
            $item->forcePrice($itemPrice);
            $sum += $itemPrice;
        }
        // any rounding weirdness (sum of items not being $customChargeamount) should go in favor of higher vat (first item)
        $items[0]->forcePrice(round($items[0]->unitPrice() + ($customChargeAmount - $sum), 2));

        $checkSum = 0;
        foreach ($items as $item) {
            $checkSum += $item->totalPrice();
        }
        if ($checkSum !== $customChargeAmount) {
            throw new \Exception("Cannot charge custom amount, sum of items [{$checkSum}] is different than charged amount [{$customChargeAmount}].");
        }

        return $items;
    }

    private function validateRecurrentPayment($recurrentPayment)
    {
        $parentRecurrentPayment = $this->recurrentPaymentsRepository->getLastWithState($recurrentPayment, RecurrentPaymentsRepository::STATE_CHARGED);
        if (!$parentRecurrentPayment) {
            return;
        }

        $diff = $parentRecurrentPayment->charge_at->diff(new DateTime());

        if ($diff->days == 0 || $parentRecurrentPayment->charge_at === $recurrentPayment->charge_at) {
            $this->recurrentPaymentsRepository->update($recurrentPayment, [
                'state' => RecurrentPaymentsRepository::STATE_SYSTEM_STOP,
                'note' => 'Fast charge',
            ]);

            throw new RecurrentPaymentFastCharge();
        }
    }
}
