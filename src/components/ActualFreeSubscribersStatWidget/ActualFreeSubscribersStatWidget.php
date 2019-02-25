<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SegmentModule\Repository\SegmentsRepository;

class ActualFreeSubscribersStatWidget extends BaseWidget
{
    private $templateName = 'actual_free_subscribers_stat_widget.latte';

    private $paymentsRepository;

    private $segmentsRepository;

    public function __construct(
        WidgetManager $widgetManager,
        PaymentsRepository $paymentsRepository,
        SegmentsRepository $segmentsRepository
    ) {
        parent::__construct($widgetManager);
        $this->paymentsRepository = $paymentsRepository;
        $this->segmentsRepository = $segmentsRepository;
    }

    public function identifier()
    {
        return 'actualfreesubscribersstatwidget';
    }

    public function render()
    {
        if ($this->segmentsRepository->exists('active-subscription-without-payment')) {
            $this->template->totalFreeSubscribersLink = $this->presenter->link(
                ':Segment:StoredSegments:show',
                $this->segmentsRepository->findByCode('active-subscription-without-payment')->id
            );
        }

        $this->template->totalFreeSubscribers = $this->paymentsRepository->freeSubscribersCount(true);
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
