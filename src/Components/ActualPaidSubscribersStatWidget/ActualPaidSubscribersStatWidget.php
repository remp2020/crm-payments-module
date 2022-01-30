<?php

namespace Crm\PaymentsModule\Components;

use Crm\SegmentsModule\Widget\DashboardSegmentValueBaseWidget;

/**
 * This widget loads number of subscribers with payment and renders line with
 * label and resulting value.
 *
 * @package Crm\PaymentsModule\Components
 */
class ActualPaidSubscribersStatWidget extends DashboardSegmentValueBaseWidget
{
    public function segmentCode(): string
    {
        return 'active-subscribers-with-paid-subscriptions';
    }

    protected function templateName(): string
    {
        return 'actual_paid_subscribers_stat_widget.latte';
    }
}
