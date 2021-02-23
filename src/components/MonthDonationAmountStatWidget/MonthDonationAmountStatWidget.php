<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\PaymentsModule\PaymentItem\DonationPaymentItem;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Nette\Utils\DateTime;

/**
 * This widget fetches sum of donations this/last month and renders line with
 * label and resulting values.
 *
 * @package Crm\PaymentsModule\Components
 */
class MonthDonationAmountStatWidget extends BaseWidget
{
    private $templateName = 'month_donation_amount_stat_widget.latte';

    private $paymentsRepository;

    public function __construct(
        WidgetManager $widgetManager,
        PaymentsRepository $paymentsRepository
    ) {
        parent::__construct($widgetManager);
        $this->paymentsRepository = $paymentsRepository;
    }

    public function identifier()
    {
        return 'monthdonationamountstatwidget';
    }

    public function render()
    {
        $this->template->thisMonthAmount = $this->paymentsRepository->paidBetween(
            DateTime::from(date('Y-m')),
            new DateTime()
        )->where(':payment_item.type = ?', DonationPaymentItem::TYPE)
            ->sum(':payment_item.amount');

        $this->template->lastMonthAmount = $this->paymentsRepository->paidBetween(
            DateTime::from('first day of -1 month 00:00'),
            DateTime::from(date('Y-m'))
        )->where(':payment_item.type = ?', DonationPaymentItem::TYPE)
            ->sum(':payment_item.amount');

        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
