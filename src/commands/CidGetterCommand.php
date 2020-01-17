<?php

namespace Crm\PaymentsModule\Commands;

use Crm\PaymentsModule\MailConfirmation\CidGetterDownloader;
use Crm\PaymentsModule\MailConfirmation\MailProcessor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CidGetterCommand extends Command
{
    /** @var CidGetterDownloader */
    private $cidMailDownloader;

    /** @var MailProcessor */
    private $mailProcessor;

    public function __construct(
        CidGetterDownloader $cidMailDownloader,
        MailProcessor $mailProcessor
    ) {
        parent::__construct();
        $this->cidMailDownloader = $cidMailDownloader;
        $this->mailProcessor = $mailProcessor;
    }

    protected function configure()
    {
        $this->setName('payments:cid_from_mail_confirmation')
            ->setDescription('get CIDs for VS')
            ->addArgument(
                'variable_symbol',
                InputArgument::REQUIRED,
                'variable symbol of originating payment that should have been confirmed'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('');
        $output->writeln('<info>***** PAYMENTS MAIL CONFIRMATION *****</info>');
        $output->writeln('');

        $this->cidMailDownloader->download(function ($mailContent) use ($output) {
            return $this->markMailProcessed($mailContent, $output);
        }, $input->getArgument('variable_symbol'));

        return 0;
    }

    private function markMailProcessed($mailContent, $output)
    {
        return !$this->mailProcessor->processMail($mailContent, $output, true);
    }
}
