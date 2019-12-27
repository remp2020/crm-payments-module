<?php

namespace Crm\PaymentsModule\Commands;

use Crm\PaymentsModule\MailConfirmation\CsobMailDownloader;
use Crm\PaymentsModule\MailConfirmation\MailProcessor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CsobMailConfirmationCommand extends Command
{
    private $mailDownloader;

    private $mailProcessor;

    public function __construct(
        CsobMailDownloader $mailDownloader,
        MailProcessor $mailProcessor
    ) {
        parent::__construct();
        $this->mailDownloader = $mailDownloader;
        $this->mailProcessor = $mailProcessor;
    }

    protected function configure()
    {
        $this->setName('payments:csob_mail_confirmation')
            ->setDescription('Check notification emails and confirm payments based on CSOB emails');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->mailDownloader->download(function ($mailContents) use ($output) {
            return $this->markMailProcessed($mailContents, $output);
        });

        return 0;
    }

    private function markMailProcessed(array $mailContents, OutputInterface $output)
    {
        $result = true;
        foreach ($mailContents as $mailContent) {
            $processed = $this->mailProcessor->processMail($mailContent, $output);
            if (!$processed) {
                $result = false;
            }
        };
        return $result;
    }
}
