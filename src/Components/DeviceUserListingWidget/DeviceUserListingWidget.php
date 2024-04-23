<?php

namespace Crm\PaymentsModule\Components\DeviceUserListingWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\UsersModule\Models\DeviceDetector;
use Nette\Database\Table\ActiveRow;

/**
 * This widget takes payment and parses device from payments user agent field.
 * Renders simple list item with result.
 *
 * @package Crm\PaymentsModule\Components
 */
class DeviceUserListingWidget extends BaseLazyWidget
{

    public function __construct(
        LazyWidgetManager $widgetManager,
        private DeviceDetector $deviceDetector,
    ) {
        parent::__construct($widgetManager);
    }

    private $templateName = 'device_user_listing_widget.latte';

    public function identifier()
    {
        return 'deviceuserlistingwidget';
    }

    public function render(ActiveRow $payment)
    {
        if (!$payment->user_agent) {
            return;
        }

        $this->deviceDetector->setUserAgent($payment->user_agent);
        $this->deviceDetector->parse();

        if ($this->deviceDetector->getModel() === '') {
            return;
        }

        $this->template->device = $this->deviceDetector->getModel();
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
