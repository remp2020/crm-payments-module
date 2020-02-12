<?php

namespace Crm\PaymentsModule\MailConfirmation;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Tomaj\BankMailsParser\Parser\TatraBankaMailParser;
use Tomaj\BankMailsParser\Parser\TatraBankaSimpleMailParser;
use Tomaj\ImapMailDownloader\Downloader;
use Tomaj\ImapMailDownloader\Email;
use Tomaj\ImapMailDownloader\MailCriteria;
use Tracy\Debugger;
use Tracy\ILogger;

class TatraBankaMailDownloader
{
    private $imapHost;

    private $imapPort;

    private $username;

    private $password;

    private $processedFolder;

    public function __construct(ApplicationConfig $config)
    {
        $this->imapHost = $config->get('tb_confirmation_host');
        $this->imapPort = $config->get('tb_confirmation_port');
        $this->username = $config->get('tb_confirmation_username');
        $this->password = $config->get('tb_confirmation_password');
        $this->processedFolder = $config->get('tb_confirmation_processed_folder');
    }

    public function download($callback)
    {
        $downloader = new Downloader($this->imapHost, $this->imapPort, $this->username, $this->password, $this->processedFolder);

        $criteria = new MailCriteria();
        $criteria->setFrom('b-mail@tatrabanka.sk');
        $criteria->setSubject('Kredit na ucte');
        $criteria->setUnseen(true);
        $downloader->fetch($criteria, function (Email $email) use ($callback) {
            $tatraBankaMailParser = new TatraBankaMailParser();
            $mailContent = $tatraBankaMailParser->parse($email->getBody());

            if (!$mailContent) {
                //                throw new \Exception("Error in parsing email");
                // nebudeme movovat spravu do spracovanych, ale aby sa oznacila ako precitana
                return false;
            }

            return $callback($mailContent);
        });

        $criteria = new MailCriteria();
        $criteria->setFrom('b-mail@tatrabanka.sk');
        $criteria->setSubject('e-commerce');
        $criteria->setUnseen(true);
        $downloader->fetch($criteria, function (Email $email) use ($callback) {
            $tatraBankaSimpleMailParser = new TatraBankaSimpleMailParser();
            $mailContent = $tatraBankaSimpleMailParser->parse($email->getBody());

            if (!$mailContent) {
                Debugger::log(
                    'Error in parsing TatraBanka (Kredit na ucte) email from: ' . $email->getDate(),
                    ILogger::WARNING
                );
                return false;
            }

            return $callback($mailContent);
        });
    }
}
