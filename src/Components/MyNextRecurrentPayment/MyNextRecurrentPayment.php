<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\PaymentsModule\RecurrentPaymentsResolver;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;

/**
 * This widget fetches last active recurrent payment for specific user
 * and renders simple bootstrap well widget showing results.
 *
 * @package Crm\PaymentsModule\Components
 */
class MyNextRecurrentPayment extends BaseLazyWidget
{
    private $templateName = 'my_next_recurrent_payment.latte';

    private $recurrentPaymentsRepository;

    private $recurrentPaymentsResolver;

    public function __construct(
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        RecurrentPaymentsResolver $recurrentPaymentsResolver,
        LazyWidgetManager $lazyWidgetManager
    ) {
        parent::__construct($lazyWidgetManager);
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
