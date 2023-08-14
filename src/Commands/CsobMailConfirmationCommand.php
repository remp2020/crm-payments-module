<?php

namespace Crm\PaymentsModule\Commands;

use Crm\PaymentsModule\MailConfirmation\CsobMailDownloader;
use Crm\PaymentsModule\MailConfirmation\MailProcessor;
use Nette\Utils\DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CsobMailConfirmationCommand extends Command
{
    public const TERMINAL_PAYMENT_CONST_SYMBOLS = ['1176', '1178'];

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

        return Command::SUCCESS;
    }

    private function markMailProcessed(array $mailContents, OutputInterface $output)
    {
        $result = true;
        foreach ($mailContents as $mailContent) {
            if (in_array($mailContent->getKs(), self::TERMINAL_PAYMENT_CONST_SYMBOLS, true)) {
                if ($mailContent->getTransactionDate()) {
                    $transactionDate = DateTime::from($mailContent->getTransactionDate());
                } else {
                    $transactionDate = new DateTime();
                }
                $output->writeln(" * Skipping email (terminal payment) <info>{$transactionDate->format('d.m.Y H:i')}</info>");
                $output->writeln("    -> VS - <info>{$mailContent->getVS()}</info> {$mailContent->getAmount()} {$mailContent->getCurrency()}");
                continue;
            }

            $processed = $this->mailProcessor->processMail($mailContent, $output);
            if (!$processed) {
                $result = false;
            }
        }
        return $result;
    }
}
