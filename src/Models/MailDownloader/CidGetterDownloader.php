<?php

namespace Crm\PaymentsModule\MailConfirmation;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Tomaj\BankMailsParser\Parser\TatraBankaSimpleMailParser;
use Tomaj\ImapMailDownloader\Downloader;
use Tomaj\ImapMailDownloader\Email;
use Tomaj\ImapMailDownloader\MailCriteria;

class CidGetterDownloader
{
    private $imapHost;

    private $imapPort;

    private $username;

    private $password;

    public function __construct(ApplicationConfig $config)
    {
        $this->imapHost = $config->get('tb_simple_confirmation_host');
        $this->imapPort = $config->get('tb_simple_confirmation_port');
        $this->username = $config->get('tb_simple_confirmation_username');
        $this->password = $config->get('tb_simple_confirmation_password');
    }

    public function download($callback, $variableSymbol)
    {
        $downloader = new Downloader($this->imapHost, $this->imapPort, $this->username, $this->password);

        $criteria = new MailCriteria();
        $criteria->setFrom('b-mail@tatrabanka.sk');
        $criteria->setSubject('e-commerce');
        $criteria->setUnseen(false);
        $criteria->setText($variableSymbol);

        $downloader->fetch($criteria, function (Email $email) use ($callback) {
            $tatraBankaMailParser = new TatraBankaSimpleMailParser();
            $mailContent = $tatraBankaMailParser->parse($email->getBody());

            if (!$mailContent) {
                // we dont want to move mail to processed mailbox, just keep it as read
                return false;
            }
            return $callback($mailContent);
        });
    }
}
