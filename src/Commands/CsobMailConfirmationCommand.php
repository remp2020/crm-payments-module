<?php

namespace Crm\PaymentsModule\Commands;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\PaymentsModule\Models\MailConfirmation\EmailInterface;
use Crm\PaymentsModule\Models\MailConfirmation\MailCriteria;
use Crm\PaymentsModule\Models\MailConfirmation\MailDownloaderInterface;
use Crm\PaymentsModule\Models\MailConfirmation\MailProcessor;
use Nette\Utils\DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tomaj\BankMailsParser\Parser\Csob\CsobMailParser;
use Tracy\Debugger;

class CsobMailConfirmationCommand extends Command
{
    public const TERMINAL_PAYMENT_CONST_SYMBOLS = ['1176', '1178'];

    private OutputInterface $output;

    public function __construct(
        private MailDownloaderInterface $mailDownloader,
        private MailProcessor $mailProcessor,
        private ApplicationConfig $applicationConfig,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('payments:csob_mail_confirmation')
            ->setDescription('Check notification emails and confirm payments based on CSOB emails');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;

        $connectionOptions = [
            'imapHost' => $this->applicationConfig->get('csob_confirmation_host'),
            'imapPort' => $this->applicationConfig->get('csob_confirmation_port'),
            'username' => $this->applicationConfig->get('csob_confirmation_username'),
            'password' => $this->applicationConfig->get('csob_confirmation_password'),
            'processedFolder' => $this->applicationConfig->get('csob_confirmation_processed_folder'),
        ];

        $criteria = new MailCriteria();
        $criteria->setFrom('notification@csob.cz');
        $criteria->setSubject('CEB Info: Zaúčtování platby');
        $criteria->setUnseen(true);
        $connectionOptions['criteria'] = $criteria;

        $this->mailDownloader->download($connectionOptions, function (EmailInterface $email) {
            $csobMailParser = new CsobMailParser();

            $mailContent = $csobMailParser->parseMulti($email->getBody());
            if (!empty($mailContent)) {
                $this->processEmail($mailContent);
                return;
            }

            Debugger::log(
                'Unable to parse CSOB statement (CEB Info: Zaúčtování platby) email from: ' . $email->getDate()->format(DATE_RFC3339),
                Debugger::ERROR,
            );
        });

        return Command::SUCCESS;
    }

    private function processEmail(array $mailContents): void
    {
        foreach ($mailContents as $mailContent) {
            if (in_array($mailContent->getKs(), self::TERMINAL_PAYMENT_CONST_SYMBOLS, true)) {
                if ($mailContent->getTransactionDate()) {
                    $transactionDate = DateTime::from($mailContent->getTransactionDate());
                } else {
                    $transactionDate = new DateTime();
                }
                $this->output->writeln(" * Skipping email (terminal payment) <info>{$transactionDate->format('d.m.Y H:i')}</info>");
                $this->output->writeln("    -> VS - <info>{$mailContent->getVS()}</info> {$mailContent->getAmount()} {$mailContent->getCurrency()}");
                continue;
            }

            $this->mailProcessor->processMail($mailContent, $this->output);
        }
    }
}
