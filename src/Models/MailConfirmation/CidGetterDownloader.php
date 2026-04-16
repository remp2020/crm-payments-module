<?php

namespace Crm\PaymentsModule\Models\MailConfirmation;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Tomaj\BankMailsParser\Parser\TatraBanka\TatraBankaSimpleMailParser;
use Tracy\Debugger;

class CidGetterDownloader
{
    public function __construct(
        private readonly ApplicationConfig $config,
        private readonly ImapClient $imapClient,
    ) {
    }

    public function download($callback, $variableSymbol): void
    {
        $imapHost = $this->config->get('tb_confirmation_host');
        $imapPort = $this->config->get('tb_confirmation_port');
        $username = $this->config->get('tb_confirmation_username');
        $password = $this->config->get('tb_confirmation_password');

        $parts = explode('/', $imapPort);
        $port = (int) $parts[0];
        $encryption = $parts[2] ?? ($parts[1] !== 'imap' ? $parts[1] : 'ssl');
        $validateCert = !in_array('novalidate-cert', $parts, true);

        $criteria = new MailCriteria();
        $criteria->setFrom('b-mail@tatrabanka.sk');
        $criteria->setSubject('e-commerce');
        $criteria->setUnseen(false);
        $criteria->setText($variableSymbol);

        $this->imapClient->fetch(
            $imapHost,
            $port,
            $encryption,
            $validateCert,
            $username,
            $password,
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
