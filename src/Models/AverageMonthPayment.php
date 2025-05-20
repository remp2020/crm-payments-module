<?php

namespace Crm\PaymentsModule\Models;

use Crm\ApplicationModule\Repositories\CacheRepository;
use Crm\UsersModule\Repositories\UserStatsRepository;
use Nette\Utils\DateTime;

class AverageMonthPayment
{
    private CacheRepository $cacheRepository;
    private UserStatsRepository $userStatsRepository;

    public function __construct(CacheRepository $cacheRepository, UserStatsRepository $userStatsRepository)
    {
        $this->cacheRepository = $cacheRepository;
        $this->userStatsRepository = $userStatsRepository;
    }

    final public function getAverageMonthPayment($forceCacheUpdate = false)
    {
        $cacheKey = 'average_month_payment';

        $callable = function () {
            return $this->userStatsRepository->getTable()
                ->where(['key' => 'avg_month_payment'])
                ->aggregation("AVG(value)");
        };
        return $this->cacheRepository->loadAndUpdate(
            $cacheKey,
            $callable,
            DateTime::from(CacheRepository::REFRESH_TIME_1_HOUR),
            $forceCacheUpdate,
        );
    }
}
