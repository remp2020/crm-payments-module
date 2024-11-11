<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Models\VatRate;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\PaymentsModule\DataProviders\PaymentItemVatDataProviderInterface;
use Crm\PaymentsModule\DataProviders\VatModeDataProviderInterface;
use Crm\PaymentsModule\Models\OneStopShop\OneStopShop;
use Crm\PaymentsModule\Models\PaymentItem\AuthorizationPaymentItem;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemInterface;
use Crm\PaymentsModule\Repositories\PaymentMetaRepository;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeItemsRepository;
use Nette\Database\Table\ActiveRow;

final class VatProcessor
{
    public const PAYMENT_META_VAT_MODE = 'vat_mode';

    public function __construct(
        private readonly OneStopShop $oneStopShop,
        private readonly DataProviderManager $dataProviderManager,
        private readonly PaymentMetaRepository $paymentMetaRepository,
        private readonly SubscriptionTypeItemsRepository $subscriptionTypeItemsRepository,
        private readonly ApplicationConfig $applicationConfig,
    ) {
    }

    public function applyVatAdjustments(
        PaymentItemContainer $paymentItemContainer,
        ActiveRow $user,
        ?ActiveRow $paymentCountry
    ): void {
        $vatMode = VatMode::B2C; // default mode

        /** @var VatModeDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            VatModeDataProviderInterface::PATH,
            VatModeDataProviderInterface::class
        );
        foreach ($providers as $provider) {
            $vatMode = $provider->getVatMode($user);
            if ($vatMode) {
                break;
            }
        }

        switch ($vatMode) {
            case VatMode::B2C:
                $this->oneStopShop->adjustPaymentItemContainerVatRates($paymentItemContainer, $paymentCountry);
                break;
            case VatMode::B2BReverseCharge:
                try {
                    $this->applyReverseCharge($paymentItemContainer);
                } catch (ReverseChargeException $e) {
                    throw new ReverseChargeException("Error creating payment for user [{$user->id}], reason:" .  $e->getMessage());
                }

                break;
            case VatMode::B2B:
            case VatMode::B2BNonEurope:
                break;
            default:
                throw new \Exception("Missing implementation for VAT mode [{$vatMode->value}]");
        }
    }

    /**
     * @param PaymentItemContainer $paymentItemContainer
     *
     * @return void
     * @throws ReverseChargeException
     */
    public function applyReverseCharge(PaymentItemContainer $paymentItemContainer): void
    {
        $containerPaymentMetas = $paymentItemContainer->getPaymentMetas();

        // If container is already marked as reverse-charge, do not continue, since the process
        // resets VAT on payment item and subtracts it. Continuing would cause VAT to be subtracted multiple times.
        if (($containerPaymentMetas[self::PAYMENT_META_VAT_MODE] ?? null) === VatMode::B2BReverseCharge->value) {
            return;
        }

        foreach ($paymentItemContainer->items() as $paymentItem) {
            // reset VAT to default state, since it might have been modified e.g. by OneStopShop
            $defaultVat = $this->paymentItemDefaultVat($paymentItem);
            $paymentItem->forceVat($defaultVat);

            // now subtract VAT
            $paymentItem->forcePrice($paymentItem->unitPriceWithoutVAT());
            $paymentItem->forceVat(0);
        }
        // explicitly mark payment as reverse-charge
        $paymentItemContainer->setPaymentMeta(self::PAYMENT_META_VAT_MODE, VatMode::B2BReverseCharge->value);
    }

    private function paymentItemDefaultVat(PaymentItemInterface $item): float
    {
        if ($item instanceof SubscriptionTypePaymentItem) {
            $subscriptionTypeItem = $this->subscriptionTypeItemsRepository->find($item->getSubscriptionTypeItemId());
            if (!$subscriptionTypeItem) {
                throw new \RuntimeException("Unable to find subscription_type_item with ID=[{$item->getSubscriptionTypeItemId()}]");
            }

            $defaultVat = $subscriptionTypeItem->vat;
            if ($defaultVat !== 0 && $item->vat() === 0 && $item->unitPrice() !== $subscriptionTypeItem->amount) {
                throw new ReverseChargeException("Non reverse-charge payment contains subscription type payment item (id=[{$subscriptionTypeItem->id}]) with 0% VAT and different price " .
                "from its original subscription_type_item prototype. This looks suspicious, it might be caused by un-marked reverse-charged PaymentItemContainer." .
                "Continuing working with the container may cause reverse-charge to be applied multiple times, therefore stopping here. Make sure to correctly mark PaymentItemContainer");
            }

            return $defaultVat;
        }

        if ($item instanceof AuthorizationPaymentItem) {
            return 0;
        }

        /** @var PaymentItemVatDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            PaymentItemVatDataProviderInterface::PATH,
            PaymentItemVatDataProviderInterface::class
        );
        foreach ($providers as $provider) {
            $vat = $provider->getVat($item);
            if ($vat) {
                return $vat;
            }
        }

        // if nothing else, apply 'vat_default' config rate
        return $this->applicationConfig->get('vat_default') ?: 0;
    }

    public function isReverseChargePayment(ActiveRow $payment): bool
    {
        return $this->paymentMetaRepository->findByPaymentAndKey($payment, self::PAYMENT_META_VAT_MODE)
                ?->value === VatMode::B2BReverseCharge->value;
    }
}
