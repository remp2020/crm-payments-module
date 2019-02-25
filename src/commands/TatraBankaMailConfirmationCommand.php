<?php

namespace Crm\PaymentsModule\Commands;

use Crm\PaymentsModule\MailConfirmation\MailProcessor;
use Crm\PaymentsModule\MailConfirmation\TatraBankaMailDownloader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TatraBankaMailConfirmationCommand extends Command
{
    private $mailDownloader;

    private $mailProcessor;

    public function __construct(
        TatraBankaMailDownloader $mailDownloader,
        MailProcessor $mailProcessor
    ) {
        parent::__construct();
        $this->mailDownloader = $mailDownloader;
        $this->mailProcessor = $mailProcessor;
    }

    protected function configure()
    {
        $this->setName('payments:tatra_banka_mail_confirmation')
            ->setDescription('Check notification emails and confirm payments based on Tatra Banka emails');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->mailDownloader->download(function ($mailContent) use ($output) {
            return $this->markMailProcessed($mailContent, $output);
        });
    }

    private function markMailProcessed($mailContent, $output)
    {
        return !$this->mailProcessor->processMail($mailContent, $output);
    }
}
