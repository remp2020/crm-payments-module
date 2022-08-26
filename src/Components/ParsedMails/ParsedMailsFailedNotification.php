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
        $this->template->listPayments = $this->parsedMailLogsRepository->formPaymentsWithWrongAmount();

        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
