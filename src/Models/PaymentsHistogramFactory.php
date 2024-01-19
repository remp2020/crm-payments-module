<?php
namespace Crm\PaymentsModule\Models;

use Crm\ApplicationModule\Models\Graphs\Criteria;
use Crm\ApplicationModule\Models\Graphs\GraphData;
use Crm\ApplicationModule\Models\Graphs\GraphDataItem;
use Crm\ApplicationModule\Repositories\CacheRepository;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Nette\Utils\JsonException;

class PaymentsHistogramFactory
{
    private $graphData;

    private $cacheRepository;

    public function __construct(GraphData $graphData, CacheRepository $cacheRepository)
    {
        $this->graphData = $graphData;
        $this->cacheRepository = $cacheRepository;
    }

    /**
     * Compute payments count histogram for given payment status
     * @param      $status
     * @param bool $forceCacheUpdate
     *
     * @return mixed
     * @throws JsonException
     */
    public function paymentsLastMonthDailyHistogram($status, $forceCacheUpdate = false)
    {
        $cacheKey = "payments_status_{$status}_last_month_daily_histogram";

        $callable = function () use ($status) {
            $graphDataItem = new GraphDataItem();
            $graphDataItem->setCriteria((new Criteria())
                ->setTableName('payments')
                ->setWhere("AND payments.status = '$status'"));

            $this->graphData->clear();
            $this->graphData->addGraphDataItem($graphDataItem);
            $this->graphData->setScaleRange('day')->setStart('-31 days');

            $data = $this->graphData->getData();
            return Json::encode($data);
        };

        return Json::decode($this->cacheRepository->loadAndUpdate(
            $cacheKey,
            $callable,
            DateTime::from(CacheRepository::REFRESH_TIME_5_MINUTES),
            $forceCacheUpdate
        ), Json::FORCE_ARRAY);
    }
}
