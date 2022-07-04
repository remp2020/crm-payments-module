<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Cache\CacheRepository;
use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\SegmentModule\SegmentWidgetInterface;
use Crm\UsersModule\Repository\UserStatsRepository;
use Nette\Database\Table\ActiveRow;

class AvgSubscriptionPaymentWidget extends BaseWidget implements SegmentWidgetInterface
{
    private string $templateName = 'avg_subscription_payment_widget.latte';

    private UserStatsRepository $userStatsRepository;
    private CacheRepository $cacheRepository;

    public function __construct(
        WidgetManager $widgetManager,
        CacheRepository $cacheRepository,
        UserStatsRepository $userStatsRepository
    ) {
        parent::__construct($widgetManager);
        $this->cacheRepository = $cacheRepository;
        $this->userStatsRepository = $userStatsRepository;
    }

    public function identifier()
    {
        return 'avgsubscriptionpaymentwidget';
    }

    public function render(ActiveRow $segment)
    {
        if (!$this->isWidgetUsable($segment)) {
            return;
        }

        $avgSubscriptionPayments = $this->cacheRepository->load($this->getCacheKey($segment));

        $this->template->avgSubscriptionPayments = $avgSubscriptionPayments->value ?? 0;
        $this->template->updatedAt = $avgSubscriptionPayments->updated_at ?? null;
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
            ->where(['key' => 'subscription_payments', 'user_id' => $userIds])
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
        return sprintf('segment_%s_subscription_payments', $segment->id);
    }
}
