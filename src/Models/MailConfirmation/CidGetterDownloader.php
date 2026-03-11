<?php

namespace Crm\PaymentsModule\Models\MailConfirmation;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Tomaj\BankMailsParser\Parser\TatraBanka\TatraBankaSimpleMailParser;
use Tracy\Debugger;

class CidGetterDownloader
{
    private string $imapHost;

    private string $imapPort;

    private string $username;

    private string $password;

    public function __construct(
        ApplicationConfig $config,
        private ImapClient $imapClient,
    ) {
        $this->imapHost = $config->get('tb_confirmation_host');
        $this->imapPort = $config->get('tb_confirmation_port');
        $this->username = $config->get('tb_confirmation_username');
        $this->password = $config->get('tb_confirmation_password');
    }

    public function download($callback, $variableSymbol)
    {
        $parts = explode('/', $this->imapPort);
        $port = (int) $parts[0];
        $encryption = $parts[2] ?? ($parts[1] !== 'imap' ? $parts[1] : 'ssl');
        $validateCert = !in_array('novalidate-cert', $parts, true);

        $criteria = new MailCriteria();
        $criteria->setFrom('b-mail@tatrabanka.sk');
        $criteria->setSubject('e-commerce');
        $criteria->setUnseen(false);
        $criteria->setText($variableSymbol);

        $this->imapClient->fetch(
            $this->imapHost,
            $port,
            $encryption,
            $validateCert,
            $this->username,
            $this->password,
            $criteria,
            function (ImapMessage $message) use ($callback) {
                $tatraBankaMailParser = new TatraBankaSimpleMailParser();
                $mailContent = $tatraBankaMailParser->parse($message->getBody());

                if (!$mailContent) {
                    Debugger::log(
                        'Unable to parse TatraBanka email (b-mail - e-commerce) email from: ' . $message->getDate()->format('Y-m-d H:i:s'),
                        Debugger::ERROR,
                    );
                    return false;
                }
                return $callback($mailContent);
            },
        );
    }
}
