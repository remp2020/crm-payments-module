<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\PaymentsModule\MailConfirmation\ParsedMailLogsRepository;

/**
 * This widgets fetches all parsed mail logs with wrong payment amount
 * and renders bootstrap callout with list of these payments.
 *
 * @package Crm\PaymentsModule\Components
 */
class ParsedMailsFailedNotification extends BaseLazyWidget
{
    private $templateName = 'parsed_mails_failed_notification.latte';

    /** @var ParsedMailLogsRepository */
    private $parsedMailLogsRepository;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        ParsedMailLogsRepository $parsedMailLogsRepository
    ) {
        parent::__construct($lazyWidgetManager);
        $this->parsedMailLogsRepository = $parsedMailLogsRepository;
    }

    public function identifier()
    {
        return 'parsedmailsfailed';
    }

    public function render($id = '')
    {
        $this->template->today = $this->parsedMailLogsRepository
            ->getDifferentAmountPaymentLogs(new \DateTime('today midnight'));
        $this->template->last7days = $this->parsedMailLogsRepository
            ->getDifferentAmountPaymentLogs((new \DateTime('-7 days'))->setTime(0, 0));
        $this->template->last30days = $this->parsedMailLogsRepository
            ->getDifferentAmountPaymentLogs((new \DateTime('-30 days'))->setTime(0, 0));

        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
