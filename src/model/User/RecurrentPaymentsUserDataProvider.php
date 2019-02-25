<?php

namespace Crm\PaymentsModule\User;

use Crm\ApplicationModule\User\UserDataProviderInterface;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;

class RecurrentPaymentsUserDataProvider implements UserDataProviderInterface
{
    private $recurrentPaymentsRepository;

    public function __construct(RecurrentPaymentsRepository $recurrentPaymentsRepository)
    {
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
    }

    public static function identifier(): string
    {
        return 'recurrent_payments';
    }

    public function data($userId)
    {
        return [];
    }

    public function download($userId)
    {
        return [];
    }

    public function downloadAttachments($userId)
    {
        return [];
    }

    public function protect($userId): array
    {
        return [];
    }

    public function delete($userId, $protectedData = [])
    {
        $this->recurrentPaymentsRepository->stoppedByGDPR($userId);
    }

    public function canBeDeleted($userId): array
    {
        return [true, null];
    }
}
