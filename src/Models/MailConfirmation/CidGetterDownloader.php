<?php

namespace Crm\PaymentsModule\Models\MailConfirmation;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Tomaj\BankMailsParser\Parser\TatraBanka\TatraBankaSimpleMailParser;
use Tomaj\ImapMailDownloader\Downloader;
use Tomaj\ImapMailDownloader\Email;
use Tomaj\ImapMailDownloader\MailCriteria;
use Tracy\Debugger;

class CidGetterDownloader
{
    private $imapHost;

    private $imapPort;

    private $username;

    private $password;

    public function __construct(ApplicationConfig $config)
    {
        $this->imapHost = $config->get('tb_confirmation_host');
        $this->imapPort = $config->get('tb_confirmation_port');
        $this->username = $config->get('tb_confirmation_username');
        $this->password = $config->get('tb_confirmation_password');
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
                Debugger::log(
                    'Unable to parse TatraBanka email (b-mail - e-commerce) email from: ' . $email->getDate(),
                    Debugger::ERROR
                );
                // email not parsed; do not call callback
                return false;
            }
            return $callback($mailContent);
        });
    }
}
