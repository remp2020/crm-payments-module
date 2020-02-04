<?php

namespace Crm\PaymentsModule\Commands;

use Crm\PaymentsModule\MailConfirmation\MailProcessor;
use Crm\PaymentsModule\MailConfirmation\TatraBankaStatementMailDownloader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TatraBankaStatementMailConfirmationCommand extends Command
{
    private $mailDownloader;

    private $mailProcessor;

    public function __construct(
        TatraBankaStatementMailDownloader $mailDownloader,
        MailProcessor $mailProcessor
    ) {
        parent::__construct();
        $this->mailDownloader = $mailDownloader;
        $this->mailProcessor = $mailProcessor;
    }

    protected function configure()
    {
        $this->setName('payments:tatra_banka_statement_mail_confirmation')
            ->setDescription('Check encrypted Tatra Banka statement emails - decrypt and confirm payments');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->mailDownloader->download(function ($mailContent) use ($output) {
            return $this->markMailProcessed($mailContent, $output);
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
