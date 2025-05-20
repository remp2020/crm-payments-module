<?php

namespace Crm\PaymentsModule\Commands;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\PaymentsModule\Models\MailConfirmation\EmailInterface;
use Crm\PaymentsModule\Models\MailConfirmation\MailDownloaderInterface;
use Crm\PaymentsModule\Models\MailConfirmation\MailProcessor;
use Crm\PaymentsModule\Models\MailParser\TatraBankaMailDecryptor;
use Crm\PaymentsModule\Models\MailParser\TatraBankaStatementMailParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tomaj\ImapMailDownloader\MailCriteria;
use Tracy\Debugger;

class TatraBankaStatementMailConfirmationCommand extends Command
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
        $this->setName('payments:tatra_banka_statement_mail_confirmation')
            ->setDescription('Check encrypted Tatra Banka statement emails - decrypt and confirm payments');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $pgpPrivateKeyPath = $this->applicationConfig->get('tatrabanka_pgp_private_key_path');
        $pgpPassphrase = $this->applicationConfig->get('tatrabanka_pgp_private_key_passphrase');

        $connectionOptions = [
            'imapHost' => $this->applicationConfig->get('tb_confirmation_host'),
            'imapPort' => $this->applicationConfig->get('tb_confirmation_port'),
            'username' => $this->applicationConfig->get('tb_confirmation_username'),
            'password' => $this->applicationConfig->get('tb_confirmation_password'),
            'processedFolder' => $this->applicationConfig->get('tb_confirmation_processed_folder'),
        ];

        $criteria = new MailCriteria();
        $criteria->setFrom('vypis_obchodnik@tatrabanka.sk');
        $criteria->setUnseen(true);
        $connectionOptions['criteria'] = $criteria;

        $this->mailDownloader->download($connectionOptions, function (EmailInterface $email) use ($pgpPrivateKeyPath, $pgpPassphrase) {
            $parser = new TatraBankaStatementMailParser(
                new TatraBankaMailDecryptor(
                    $pgpPrivateKeyPath,
                    $pgpPassphrase,
                ),
            );
            $mailContents = $parser->parseMulti($email->getBody());

            if (!$mailContents) {
                Debugger::log(
                    'Unable to parse TatraBanka statement (vypis_obchodnik) email from: ' . $email->getDate(),
                    Debugger::ERROR,
                );
                // email not parsed; do not call callback
                return;
            }

            foreach ($mailContents as $mailContent) {
                $this->mailProcessor->processMail($mailContent, $this->output);
            }
        });

        return Command::SUCCESS;
    }
}
