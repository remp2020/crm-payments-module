<?php

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\DataProvider\DataProviderException;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SubscriptionsModule\DataProviders\PaymentFromVariableSymbolDataProviderInterface;
use Nette\Database\Table\ActiveRow;

class PaymentFromVariableSymbolDataProvider implements PaymentFromVariableSymbolDataProviderInterface
{
    private $paymentsRepository;

    public function __construct(PaymentsRepository $paymentsRepository)
    {
        $this->paymentsRepository = $paymentsRepository;
    }

    public function provide(array $params): ?ActiveRow
    {
        if (!isset($params['variableSymbol'])) {
            throw new DataProviderException('variableSymbol param missing');
        }

        $payment = $this->paymentsRepository->findByVs($params['variableSymbol']);
        return $payment ?: null;
    }
}
