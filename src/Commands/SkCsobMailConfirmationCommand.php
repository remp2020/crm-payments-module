<?php

namespace Crm\PaymentsModule\Commands;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\PaymentsModule\Models\MailConfirmation\EmailInterface;
use Crm\PaymentsModule\Models\MailConfirmation\MailDownloaderInterface;
use Crm\PaymentsModule\Models\MailConfirmation\MailProcessor;
use Crm\PaymentsModule\Models\MailParser\SkCsobMailParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tomaj\ImapMailDownloader\MailCriteria;
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

    protected function execute(InputInterface $input, OutputInterface $output)
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

            // csob changed encoding for some emails and ImapDownloader doesn't provide the header
            // this is a dummy check to verify what encoding was used to encode the content of email
            $mailContent = $skCsobMailParser->parseMulti(base64_decode($email->getBody()));
            if (!empty($mailContent)) {
                $this->processEmail($mailContent);
                return;
            }

            $mailContent = $skCsobMailParser->parseMulti(quoted_printable_decode($email->getBody()));
            if (!empty($mailContent)) {
                $this->processEmail($mailContent);
                return;
            }

            Debugger::log(
                'Unable to parse CSOB statement (ČSOB Info 24 - Avízo) email from: ' . $email->getDate(),
                Debugger::ERROR
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
