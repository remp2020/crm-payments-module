<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Components\PauseSubscriptionWidget;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Models\NowTrait;
use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\PaymentsModule\DataProviders\IsSubscriptionPausableDataProviderInterface;
use Crm\PaymentsModule\Repositories\SubscriptionPausesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Nette\Database\Table\ActiveRow;

class PauseSubscriptionWidget extends BaseLazyWidget
{
    use NowTrait;

    private string $templateName = 'pause_subscription_widget.latte';

    public function __construct(
        LazyWidgetManager $widgetManager,
        private readonly SubscriptionsRepository $subscriptionsRepository,
        private readonly SubscriptionPausesRepository $subscriptionPausesRepository,
        private readonly ApplicationConfig $applicationConfig,
        private readonly DataProviderManager $dataProviderManager,
    ) {
        parent::__construct($widgetManager);
    }

    public function render(ActiveRow $subscription)
    {
        $config = $this->applicationConfig->get('allow_pause_subscriptions');
        if (!$config) {
            return;
        }

        if (!$this->isSubscriptionPausable($subscription)) {
            return;
        }

        $this->template->subscription = $subscription;
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }

    private function isSubscriptionPausable(ActiveRow $subscription): bool
    {
        $now = $this->getNow();

        // already stopped
        if ($subscription->start_time == $subscription->end_time) {
            return false;
        }

        if ($subscription->end_time <= $now || $subscription->start_time > $now) {
            return false;
        }

        if ($this->subscriptionPausesRepository->hasScheduledOrActivePause($subscription)) {
            return false;
        }

        /** @var IsSubscriptionPausableDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            'payments.dataprovider.pause_subscription_widget.is_pausable',
            IsSubscriptionPausableDataProviderInterface::class,
        );
        foreach ($providers as $sorting => $provider) {
            $isPausable = $provider->isSubscriptionPausable($subscription);
            if (!$isPausable) {
                return false;
            }
        }

        if ($this->subscriptionsRepository->actualUserSubscriptions($subscription->user_id)->count('*') > 1) {
            // user has more than one active subscription, so we don't allow pausing
            return false;
        }

        return true;
    }
}
