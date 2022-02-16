<?php

namespace Crm\PaymentsModule\Models;

use Crm\ApplicationModule\Cache\CacheRepository;
use Crm\UsersModule\Repository\UserMetaRepository;
use Nette\Utils\DateTime;

class AverageMonthPayment
{
    private CacheRepository $cacheRepository;
    private UserMetaRepository $userMetaRepository;

    public function __construct(CacheRepository $cacheRepository, UserMetaRepository $userMetaRepository)
    {
        $this->cacheRepository = $cacheRepository;
        $this->userMetaRepository = $userMetaRepository;
    }

    final public function getAverageMonthPayment($forceCacheUpdate = false)
    {
        $cacheKey = 'average_month_payment';

        $callable = function () {
            return $this->userMetaRepository->getTable()
                ->where(['key' => 'avg_month_payment'])
                ->aggregation("AVG(value)");
        };
        return $this->cacheRepository->loadAndUpdate(
            $cacheKey,
            $callable,
            DateTime::from(CacheRepository::REFRESH_TIME_1_HOUR),
            $forceCacheUpdate
        );
    }
}
