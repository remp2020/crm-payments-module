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
        $this->imapHost = $config->get('confirmation_mail_host');
        $this->imapPort = $config->get('confirmation_mail_port');
        $this->username = $config->get('confirmation_mail_username');
        $this->password = $config->get('confirmation_mail_password');
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
