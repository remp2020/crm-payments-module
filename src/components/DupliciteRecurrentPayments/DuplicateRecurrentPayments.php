<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Nette\Application\BadRequestException;
use Nette\Localization\ITranslator;

class DuplicateRecurrentPayments extends BaseWidget
{
    private $templateName = 'duplicate_recurrent_payments.latte';

    /** @var RecurrentPaymentsRepository */
    private $recurrentPaymentsRepository;

    /** @var ITranslator */
    private $translator;

    public function __construct(
        WidgetManager $widgetManager,
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        ITranslator $translator
    ) {
        parent::__construct($widgetManager);
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->translator = $translator;
    }

    public function header()
    {
        return $this->translator->translate('payments.admin.recurrent.duplicates.title');
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

        $this->flashMessage($this->translator->translate('payments.admin.component.duplicate_recurrent_payments.messages.recurrent_profile_stopped'));
        $this->presenter->redirect('this');
    }
}
