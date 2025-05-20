<?php

namespace Crm\PaymentsModule\Components\SubscribersWithPaymentWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use Crm\SegmentModule\Repositories\SegmentsValuesRepository;
use DateTime;
use Nette\Utils\Random;

/**
 * This widget takes segment code and date modifier and than fetches subscribers value
 * without modifier and with modifier. Renders simple bootstrap badge with difference value
 *
 * @package Crm\PaymentsModule\Components
 */
class SubscribersWithPaymentWidget extends BaseLazyWidget
{
    private $templateName = 'subscribers_with_payment_widget.latte';

    private $segmentsRepository;

    private $segmentsValuesRepository;

    private $segmentCode;

    private $date;

    private $identifier;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        SegmentsRepository $segmentsRepository,
        SegmentsValuesRepository $segmentsValuesRepository,
    ) {
        parent::__construct($lazyWidgetManager);

        $this->segmentsRepository = $segmentsRepository;
        $this->segmentsValuesRepository = $segmentsValuesRepository;
        $this->identifier = 'subscriberswithpaymentwidget_' . Random::generate();
    }

    public function header()
    {
        return 'Subscription';
    }

    public function identifier()
    {
        return $this->identifier;
    }

    public function setSegmentCode($code)
    {
        $this->segmentCode = $code;
        return $this;
    }

    public function setDateModifier($str)
    {
        $this->date = (new DateTime)->modify($str);
        return $this;
    }

    public function render()
    {
        if (!$this->segmentsRepository->exists($this->segmentCode)) {
            throw new \Exception('trying to render SubscribersWithPaymentWidget with non-existing segment: ' . $this->segmentCode);
        }

        $date = new DateTime;
        $daysDiff = $date->diff($this->date)->days;

        $now = $this->segmentsValuesRepository->valuesBySegmentCode($this->segmentCode)
            ->order('date DESC')
            ->limit(1)
            ->select('*')
            ->fetch();

        $then = $this->segmentsValuesRepository->valuesBySegmentCode($this->segmentCode)
            ->where('date <= ?', $this->date)
            ->order('date DESC')
            ->limit(1)
            ->select('*')
            ->fetch();

        $this->template->date = $this->date;
        $this->template->daysDiff = $daysDiff;
        $this->template->now = $now ? $now->value : 0;
        $this->template->then = $then ? $then->value: 0;

        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
