<?php

namespace Crm\PaymentsModule\Components\SubscribersWithPaymentWidget;

use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use Crm\SegmentModule\Repositories\SegmentsValuesRepository;

class SubscribersWithPaymentWidgetFactory
{
    const DEFAULT_SEGMENT = 'active-subscribers-with-paid-subscriptions';

    protected $lazyWidgetManager;

    protected $segmentsRepository;

    protected $segmentsValuesRepository;

    protected $segmentCode;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        SegmentsRepository $segmentsRepository,
        SegmentsValuesRepository $segmentsValuesRepository,
    ) {
        $this->lazyWidgetManager = $lazyWidgetManager;
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
            $this->lazyWidgetManager,
            $this->segmentsRepository,
            $this->segmentsValuesRepository,
        ))->setSegmentCode($segmentCode);
    }
}
