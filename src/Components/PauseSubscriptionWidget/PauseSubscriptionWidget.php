<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Components\PauseSubscriptionWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\PaymentsModule\Models\Subscription\SubscriptionPauser;
use Nette\Database\Table\ActiveRow;

class PauseSubscriptionWidget extends BaseLazyWidget
{
    private string $templateName = 'pause_subscription_widget.latte';

    public function __construct(
        LazyWidgetManager $widgetManager,
        private readonly SubscriptionPauser $subscriptionPauser,
    ) {
        parent::__construct($widgetManager);
    }

    public function render(ActiveRow $subscription)
    {
        if (!$this->subscriptionPauser->isSubscriptionPausable($subscription)) {
            return;
        }

        $this->template->subscription = $subscription;
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
