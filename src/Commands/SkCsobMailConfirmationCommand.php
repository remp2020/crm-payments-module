<?php

namespace Crm\PaymentsModule\Commands;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\PaymentsModule\Models\MailConfirmation\EmailInterface;
use Crm\PaymentsModule\Models\MailConfirmation\MailCriteria;
use Crm\PaymentsModule\Models\MailConfirmation\MailDownloaderInterface;
use Crm\PaymentsModule\Models\MailConfirmation\MailProcessor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tomaj\BankMailsParser\Parser\Csob\SkCsobMailParser;
use Tracy\Debugger;

class SkCsobMailConfirmationCommand extends Command
{
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
        $this->setName('payments:sk_csob_mail_confirmation')
            ->setDescription('Check notification emails and confirm payments based on slovak CSOB emails');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;

        $connectionOptions = [
            'imapHost' => $this->applicationConfig->get('sk_csob_confirmation_host'),
            'imapPort' => $this->applicationConfig->get('sk_csob_confirmation_port'),
            'username' => $this->applicationConfig->get('sk_csob_confirmation_username'),
            'password' => $this->applicationConfig->get('sk_csob_confirmation_password'),
            'processedFolder' => $this->applicationConfig->get('sk_csob_confirmation_processed_folder'),
        ];

        $criteria = new MailCriteria();
        $criteria->setFrom('AdminTBS@csob.sk');
        $criteria->setSubject('ČSOB Info 24 - Avízo');
        $criteria->setUnseen(true);
        $connectionOptions['criteria'] = $criteria;

        $this->mailDownloader->download($connectionOptions, function (EmailInterface $email) {
            $skCsobMailParser = new SkCsobMailParser();

            $mailContent = $skCsobMailParser->parseMulti($email->getBody());
            if (!empty($mailContent)) {
                $this->processEmail($mailContent);
                return;
            }

            Debugger::log(
                'Unable to parse CSOB statement (ČSOB Info 24 - Avízo) email from: ' . $email->getDate()->format(DATE_RFC3339),
                Debugger::ERROR,
            );
        });

        return Command::SUCCESS;
    }

    private function processEmail(array $mailContents): void
    {
        foreach ($mailContents as $mailContent) {
            $this->mailProcessor->processMail($mailContent, $this->output);
        }
    }
}
