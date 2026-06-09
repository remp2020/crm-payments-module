<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\DataProviders;

use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\SalesFunnelModule\DataProviders\UserStoredCardDataProviderInterface;
use Nette\Database\Table\ActiveRow;

class UserStoredCardDataProvider implements UserStoredCardDataProviderInterface
{
    public function __construct(
        private readonly RecurrentPaymentsRepository $recurrentPaymentsRepository,
    ) {
    }

    public function canUserUseStoredCard(ActiveRow $payment, ActiveRow $user): bool
    {
        return $this->recurrentPaymentsRepository->hasStoredCard($user, $payment->payment_gateway);
    }
}
