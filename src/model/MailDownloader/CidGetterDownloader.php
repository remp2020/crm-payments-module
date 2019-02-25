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

    public function download($callback)
    {
        $downloader = new Downloader($this->imapHost, $this->imapPort, $this->username, $this->password);

        $criteria = new MailCriteria();
        $criteria->setFrom('b-mail@tatrabanka.sk');
        $criteria->setSubject('e-commerce');
        $criteria->setUnseen(false);
        $vs = [7967692806, 5540382373, 9954699320, 1389491111, 312235870, 4542042628, 2120556174, 8569514443, 4135481249, 8001128555, 5970970263, 1433648187, 3169437619, 6545503065, 6939654055, 1622051577, 7704323525, 5663876029, 9760642847, 5919222501, 9394554425, 1416596499, 9884367851, 2035123150, 3098938832, 8626761926, 4878721464, 803974288, 6367815183, 3085613428, 5007449808, 5218554774, 9169547147, 7505061583, 9290383694, 845839686, 3163710565, 9139950024, 4586644799, 8856358429, 4836843176, 1406374881, 118153505];
        foreach ($vs as $v) {
            $criteria->setText($v);

            $downloader->fetch($criteria, function (Email $email) use ($callback) {
                $tatraBankaMailParser = new TatraBankaSimpleMailParser();

                $mailContent = $tatraBankaMailParser->parse($email->getBody());

                if (!$mailContent) {
                    //                throw new \Exception("Error in parsing email");
                    // nebudeme movovat spravu do spracovanych, ale aby sa oznacila ako precitana
                    return false;
                }
                return $callback($mailContent);
            });
        }
    }
}
