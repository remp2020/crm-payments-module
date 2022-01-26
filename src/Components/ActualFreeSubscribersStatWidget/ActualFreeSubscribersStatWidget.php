<?php

namespace Crm\PaymentsModule\Components;

use Crm\SegmentsModule\Widget\DashboardSegmentValueBaseWidget;

/**
 * This widget loads number of free subscribers and renders line with
 * label and resulting value.
 *
 * @package Crm\PaymentsModule\Components
 */
class ActualFreeSubscribersStatWidget extends DashboardSegmentValueBaseWidget
{
    public function segmentCode(): string
    {
        return 'active-subscribers-having-only-non-paid-subscriptions';
    }

    protected function templateName(): string
    {
        return 'actual_free_subscribers_stat_widget.latte';
    }
}
