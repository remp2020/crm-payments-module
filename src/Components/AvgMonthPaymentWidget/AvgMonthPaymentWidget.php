<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Cache\CacheRepository;
use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\SegmentModule\Models\SegmentWidgetInterface;
use Crm\UsersModule\Repositories\UserStatsRepository;
use Nette\Database\Table\ActiveRow;

class AvgMonthPaymentWidget extends BaseLazyWidget implements SegmentWidgetInterface
{
    private string $templateName = 'avg_month_payment_widget.latte';
    private CacheRepository $cacheRepository;
    private UserStatsRepository $userStatsRepository;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        UserStatsRepository $userStatsRepository,
        CacheRepository $cacheRepository
    ) {
        parent::__construct($lazyWidgetManager);
        $this->cacheRepository = $cacheRepository;
        $this->userStatsRepository = $userStatsRepository;
    }

    public function identifier()
    {
        return 'avgmonthpaymentwidget';
    }

    public function render(ActiveRow $segment)
    {
        if (!$this->isWidgetUsable($segment)) {
            return;
        }

        $avgMonthPayment = $this->cacheRepository->load($this->getCacheKey($segment));

        $this->template->avgMonthPayment = $avgMonthPayment->value ?? 0;
        $this->template->updatedAt = $avgMonthPayment->updated_at ?? null;
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }

    public function recalculate(ActiveRow $segment, array $userIds): void
    {
        if (!$this->isWidgetUsable($segment)) {
            return;
        }

        $result = $this->userStatsRepository
            ->getTable()
            ->select('COALESCE(SUM(value), 0) AS sum')
            ->where(['key' => 'avg_month_payment', 'user_id' => $userIds])
            ->fetch();

        $value = 0;
        if ($result !== null && count($userIds) !== 0) {
            $value = $result->sum / count($userIds);
        }

        $this->cacheRepository->updateKey($this->getCacheKey($segment), $value);
    }

    private function isWidgetUsable($segment): bool
    {
        return $segment->table_name === 'users';
    }

    private function getCacheKey($segment): string
    {
        return sprintf('segment_%s_avg_month_payment', $segment->id);
    }
}
