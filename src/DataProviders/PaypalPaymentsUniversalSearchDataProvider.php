<?php

namespace Crm\PaymentsModule\DataProviders;

use Contributte\Translation\Translator;
use Crm\AdminModule\Models\UniversalSearchDataProviderInterface;
use Crm\ApplicationModule\Helpers\UserDateHelper;
use Crm\PaymentsModule\Models\Gateways\Paypal;
use Crm\PaymentsModule\Models\Gateways\PaypalReference;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Nette\Application\LinkGenerator;

class PaypalPaymentsUniversalSearchDataProvider implements UniversalSearchDataProviderInterface
{
    public function __construct(
        private readonly PaymentsRepository $paymentsRepository,
        private readonly LinkGenerator $linkGenerator,
        private readonly Translator $translator,
        private readonly UserDateHelper $userDateHelper
    ) {
    }

    public function provide(array $params): array
    {
        $result = [];
        $term = $params['term'];

        // Valid PayPal transaction ID is 17 characters long.
        if (strlen($term) === 17) {
            $payment = $this->paymentsRepository->getTable()
                ->where([
                    ':payment_meta.key' => 'transaction_id',
                    ':payment_meta.value' => $term,
                    'payment_gateway.code' => [Paypal::GATEWAY_CODE, PaypalReference::GATEWAY_CODE]
                ])
                ->fetch();

            if ($payment) {
                $text = "{$payment->user->email} - {$payment->variable_symbol}";
                if ($payment->paid_at) {
                    $text .= ' - ' . $this->userDateHelper->process($payment->paid_at);
                }
                $result[$this->translator->translate('payments.data_provider.universal_search.payment_group')][] = [
                    'id' => 'payment_' . $payment->id,
                    'text' => $text,
                    'url' => $this->linkGenerator->link('Payments:PaymentsAdmin:show', ['id' => $payment->id]),
                ];
            }
        }

        return $result;
    }
}
