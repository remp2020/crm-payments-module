<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Nette\Application\BadRequestException;

class DuplicateRecurrentPayments extends BaseWidget
{
    private $templateName = 'duplicate_recurrent_payments.latte';

    /** @var RecurrentPaymentsRepository */
    private $recurrentPaymentsRepository;

    public function __construct(
        WidgetManager $widgetManager,
        RecurrentPaymentsRepository $recurrentPaymentsRepository
    ) {
        parent::__construct($widgetManager);
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
    }

    public function header()
    {
        return 'Duplicitné recurrentné platby';
    }

    public function identifier()
    {
        return 'duplicaterecurrentpayment';
    }

    public function render()
    {
        $this->template->recurrentPayments = $this->recurrentPaymentsRepository->getDuplicate();

        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }

    public function handleStopRecurrentPayment($recurrentPaymentId)
    {
        $recurrent = $this->recurrentPaymentsRepository->stoppedByAdmin($recurrentPaymentId);
        if (!$recurrent) {
            throw new BadRequestException();
        }

        $this->flashMessage('Rekurentný profil bol upravený');
        $this->presenter->redirect('this');
    }
}
