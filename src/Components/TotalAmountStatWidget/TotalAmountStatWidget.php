<?php

namespace Crm\PaymentsModule\Components\TotalAmountStatWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\PaymentsModule\Repositories\PaymentsRepository;

/**
 * This widget fetches total amount of payments and renders line with
 * label and resulting value.
 *
 * @package Crm\PaymentsModule\Components
 */
class TotalAmountStatWidget extends BaseLazyWidget
{
    private $templateName = 'total_amount_stat_widget.latte';

    private $paymentsRepository;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        PaymentsRepository $paymentsRepository
    ) {
        parent::__construct($lazyWidgetManager);
        $this->paymentsRepository = $paymentsRepository;
    }

    public function identifier()
    {
        return 'totalamountstatwidget';
    }

    public function render()
    {
        $this->template->totalAmount = $this->paymentsRepository->totalAmountSum(true);
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
