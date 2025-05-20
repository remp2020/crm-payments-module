<?php

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\UsersModule\DataProviders\ClaimUserDataProviderInterface;

class PaymentsClaimUserDataProvider implements ClaimUserDataProviderInterface
{
    private $paymentsRepository;

    public function __construct(
        PaymentsRepository $paymentsRepository,
    ) {
        $this->paymentsRepository = $paymentsRepository;
    }

    public function provide(array $params): void
    {
        if (!isset($params['unclaimedUser'])) {
            throw new DataProviderException('unclaimedUser param missing');
        }
        if (!isset($params['loggedUser'])) {
            throw new DataProviderException('loggedUser param missing');
        }

        $unclaimedUserPayments = $this->paymentsRepository->userPayments($params['unclaimedUser']->id)->fetchAll();
        foreach ($unclaimedUserPayments as $unclaimedUserPayment) {
            $this->paymentsRepository->update($unclaimedUserPayment, ['user_id' => $params['loggedUser']->id]);
        }
    }
}
