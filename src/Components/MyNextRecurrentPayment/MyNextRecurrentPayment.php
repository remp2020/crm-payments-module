<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\PaymentsModule\RecurrentPaymentsResolver;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;

/**
 * This widget fetches last active recurrent payment for specific user
 * and renders simple bootstrap well widget showing results.
 *
 * @package Crm\PaymentsModule\Components
 */
class MyNextRecurrentPayment extends BaseWidget
{
    private $templateName = 'my_next_recurrent_payment.latte';

    private $recurrentPaymentsRepository;

    private $recurrentPaymentsResolver;

    /** @var WidgetManager */
    protected $widgetManager;

    public function __construct(
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        RecurrentPaymentsResolver $recurrentPaymentsResolver,
        WidgetManager $widgetManager
    ) {
        parent::__construct($widgetManager);
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->recurrentPaymentsResolver = $recurrentPaymentsResolver;
    }

    public function identifier()
    {
        return 'mynextrecurrentpayment';
    }

    public function render()
    {
        $this->template->recurrentPayment = $this->recurrentPaymentsRepository
            ->userRecurrentPayments($this->getPresenter()->getUser()->getId())
            ->where('state = "active"')
            ->order('charge_at ASC')
            ->limit(1)->fetch();

        $this->template->resolver = $this->recurrentPaymentsResolver;

        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
