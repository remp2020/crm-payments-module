<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Nette\Database\Table\ActiveRow;

/**
 * This widget takes payment and parses device from payments user agent field.
 * Renders simple list item with result.
 *
 * @package Crm\PaymentsModule\Components
 */
class DeviceUserListingWidget extends BaseLazyWidget
{
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

        $userAgent = new \Sinergi\BrowserDetector\UserAgent($payment->user_agent);
        $device = new \Sinergi\BrowserDetector\Device($userAgent);

        if ($device->getName() === 'unknown') {
            return;
        }

        $this->template->device = $device;
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
