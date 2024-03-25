<?php

namespace Crm\PaymentsModule\Commands;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\PaymentsModule\Models\MailConfirmation\EmailInterface;
use Crm\PaymentsModule\Models\MailConfirmation\MailDownloaderInterface;
use Crm\PaymentsModule\Models\MailConfirmation\MailProcessor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tomaj\BankMailsParser\Parser\TatraBanka\TatraBankaMailParser;
use Tomaj\BankMailsParser\Parser\TatraBanka\TatraBankaSimpleMailParser;
use Tomaj\ImapMailDownloader\MailCriteria;
use Tracy\Debugger;

class TatraBankaMailConfirmationCommand extends Command
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
        $this->setName('payments:tatra_banka_mail_confirmation')
            ->setDescription('Check notification emails and confirm payments based on Tatra Banka emails');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $connectionOptions = [
            'imapHost' => $this->applicationConfig->get('tb_confirmation_host'),
            'imapPort' => $this->applicationConfig->get('tb_confirmation_port'),
            'username' => $this->applicationConfig->get('tb_confirmation_username'),
            'password' => $this->applicationConfig->get('tb_confirmation_password'),
            'processedFolder' => $this->applicationConfig->get('tb_confirmation_processed_folder'),
        ];

        $criteria = new MailCriteria();
        $criteria->setFrom('b-mail@tatrabanka.sk');
        $criteria->setSubject('Kredit na ucte');
        $criteria->setUnseen(true);
        $connectionOptions['criteria'] = $criteria;

        $this->mailDownloader->download($connectionOptions, function (EmailInterface $email) {
            $tatraBankaMailParser = new TatraBankaMailParser();
            $mailContent = $tatraBankaMailParser->parse($email->getBody());

            if (!$mailContent) {
                Debugger::log(
                    'Unable to parse TatraBanka email (b-mail - Kredit na ucte) email from: ' . $email->getDate(),
                    Debugger::ERROR
                );
                // email not parsed; do not process mail
                return;
            }

            return $this->mailProcessor->processMail($mailContent, $this->output);
        });

        $criteria = new MailCriteria();
        $criteria->setFrom('b-mail@tatrabanka.sk');
        $criteria->setSubject('e-commerce');
        $criteria->setUnseen(true);

        $options = array_merge($connectionOptions, ['criteria' => $criteria]);
        $this->mailDownloader->download($options, function (EmailInterface $email) {
            $tatraBankaSimpleMailParser = new TatraBankaSimpleMailParser();
            $mailContent = $tatraBankaSimpleMailParser->parse($email->getBody());

            if (!$mailContent) {
                Debugger::log(
                    'Unable to parse TatraBanka email (b-mail - e-commerce) email from: ' . $email->getDate(),
                    Debugger::ERROR
                );
                // email not parsed; do not process mail
                return;
            }

            return $this->mailProcessor->processMail($mailContent, $this->output);
        });

        return Command::SUCCESS;
    }
}
