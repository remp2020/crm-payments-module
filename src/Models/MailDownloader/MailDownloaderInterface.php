<?php

namespace Crm\PaymentsModule\Models\MailDownloader;

interface MailDownloaderInterface
{
    /**
     * @param array $options
     * @param callable $callback(Crm\PaymentsModule\Models\MailDownloader\EmailInterface $email)
     * @return void
     */
    public function download(array $options, callable $callback): void;
}
