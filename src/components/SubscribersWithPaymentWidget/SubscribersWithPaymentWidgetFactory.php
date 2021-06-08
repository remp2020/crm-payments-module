<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Crm\SegmentModule\Repository\SegmentsValuesRepository;

class SubscribersWithPaymentWidgetFactory
{
    const DEFAULT_SEGMENT = 'active-subscribers-with-paid-subscriptions';

    protected $widgetManager;

    protected $segmentsRepository;

    protected $segmentsValuesRepository;

    protected $segmentCode;

    public function __construct(
        WidgetManager $widgetManager,
        SegmentsRepository $segmentsRepository,
        SegmentsValuesRepository $segmentsValuesRepository
    ) {
        $this->widgetManager = $widgetManager;
        $this->segmentsRepository = $segmentsRepository;
        $this->segmentsValuesRepository = $segmentsValuesRepository;
    }

    public function setSegment($code)
    {
        $this->segmentCode = $code;
        return $this;
    }

    public function create()
    {
        $segmentCode = $this->segmentCode ?? self::DEFAULT_SEGMENT;

        return (new SubscribersWithPaymentWidget(
            $this->widgetManager,
            $this->segmentsRepository,
            $this->segmentsValuesRepository
        ))->setSegmentCode($segmentCode);
    }
}
