<?php

namespace Crm\PaymentsModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderException;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\UsersModule\User\ClaimUserDataProviderInterface;

class RecurrentPaymentsClaimUserDataProvider implements ClaimUserDataProviderInterface
{
    private $recurrentPaymentsRepository;

    public function __construct(
        RecurrentPaymentsRepository $recurrentPaymentsRepository
    ) {
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
    }

    public function provide(array $params): void
    {
        if (!isset($params['unclaimedUser'])) {
            throw new DataProviderException('unclaimedUser param missing');
        }
        if (!isset($params['loggedUser'])) {
            throw new DataProviderException('loggedUser param missing');
        }

        $unclaimedUserRecurrentPayments = $this->recurrentPaymentsRepository->userRecurrentPayments($params['unclaimedUser']->id)->fetchAll();
        foreach ($unclaimedUserRecurrentPayments as $unclaimedUserRecurrentPayment) {
            $this->recurrentPaymentsRepository->update($unclaimedUserRecurrentPayment, ['user_id' => $params['loggedUser']->id]);
        }
    }
}
