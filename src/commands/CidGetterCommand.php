<?php

namespace Crm\PaymentsModule\Commands;

use Crm\PaymentsModule\MailConfirmation\CidGetterDownloader;
use Crm\PaymentsModule\MailConfirmation\MailProcessor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CidGetterCommand extends Command
{
    /** @var CidGetterDownloader */
    private $mailDownloader;

    /** @var MailProcessor */
    private $mailProcessor;

    public function __construct(
        CidGetterDownloader $mailDownloader,
        MailProcessor $mailProcessor
    ) {
        parent::__construct();
        $this->mailDownloader = $mailDownloader;
        $this->mailProcessor = $mailProcessor;
    }

    protected function configure()
    {
        $this->setName('payments:cidGetter')
            ->setDescription('get CIDs for VS');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('');
        $output->writeln('<info>***** PAYMENTS MAIL CONFIRMATION *****</info>');
        $output->writeln('');

        $this->mailDownloader->download(function ($mailContent) use ($output) {
            return $this->markMailProcessed($mailContent, $output);
        });
    }

    private function markMailProcessed($mailContent, $output)
    {
        return !$this->mailProcessor->processMail($mailContent, $output, true);
    }
}
