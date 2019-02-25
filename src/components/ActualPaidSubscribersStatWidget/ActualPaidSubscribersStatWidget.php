<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SegmentModule\Repository\SegmentsRepository;

class ActualPaidSubscribersStatWidget extends BaseWidget
{
    private $templateName = 'actual_paid_subscribers_stat_widget.latte';

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
        return 'actualpaidsubscribersstatwidget';
    }

    public function render()
    {
        if ($this->segmentsRepository->exists('active-subscription-with-payment')) {
            $this->template->totalPaidSubscribersLink = $this->presenter->link(
                ':Segment:StoredSegments:show',
                $this->segmentsRepository->findByCode('active-subscription-with-payment')->id
            );
        }

        $this->template->totalPaidSubscribers = $this->paymentsRepository->paidSubscribersCount(true);
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
