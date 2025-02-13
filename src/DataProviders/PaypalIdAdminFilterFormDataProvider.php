<?php

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\ApplicationModule\UI\Form;
use Crm\PaymentsModule\Models\Gateways\Paypal;
use Crm\PaymentsModule\Models\Gateways\PaypalReference;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Nette\Database\Table\Selection;

class PaypalIdAdminFilterFormDataProvider implements AdminFilterFormDataProviderInterface
{
    public function __construct(private readonly PaymentsRepository $paymentsRepository)
    {
    }

    public function provide(array $params): Form
    {
        if (!isset($params['form'])) {
            throw new DataProviderException('missing [form] within data provider params');
        }
        return $params['form'];
    }

    public function filter(Selection $selection, array $formData): Selection
    {
        $externalId = $formData['external_id'] ?? null;
        if (!$externalId) {
            return $selection;
        }

        $results = $this->paymentsRepository->getTable()
            ->where([
                ':payment_meta.key' => 'transaction_id',
                ':payment_meta.value' => $externalId,
                'payment_gateway.code' => [Paypal::GATEWAY_CODE, PaypalReference::GATEWAY_CODE]
            ])
            ->fetchPairs('id', 'id');

        if (count($results) > 0) {
            $selection->where('payments.id IN (?)', array_keys($results));
        }

        return $selection;
    }
}
