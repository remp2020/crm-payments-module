<?php

namespace Crm\PaymentsModule\Components\DuplicateRecurrentPayments;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Nette\Application\BadRequestException;
use Nette\Localization\Translator;

/**
 * This widgets fetches and renders bootstrap table with all duplicate recurrent payments.
 * Also allows deactivating recurrent payment.
 *
 * @package Crm\PaymentsModule\Components
 */
class DuplicateRecurrentPayments extends BaseLazyWidget
{
    private $templateName = 'duplicate_recurrent_payments.latte';

    /** @var RecurrentPaymentsRepository */
    private $recurrentPaymentsRepository;

    /** @var Translator */
    private $translator;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        Translator $translator
    ) {
        parent::__construct($lazyWidgetManager);
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
