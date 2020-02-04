<?php

namespace Crm\PaymentsModule\MailParser;

class TatraBankaMailDecryptor
{
    private $privateKeyPath;

    private $passphrase;

    public function __construct(
        string $privateKeyPath,
        string $passphrase
    ) {
        $this->privateKeyPath = $privateKeyPath;
        $this->passphrase = $passphrase;
    }

    public function decrypt($contents)
    {
        if (!$this->privateKeyPath || !file_exists($this->privateKeyPath)) {
            throw new \Exception('missing path to TatraBanka PGP private key in config');
        }

        $privateKey = \OpenPGP_Message::parse(file_get_contents($this->privateKeyPath));
        $msg = null;
        foreach ($privateKey as $p) {
            if (!($p instanceof \OpenPGP_SecretKeyPacket)) {
                continue;
            }

            $privateKey = \OpenPGP_Crypt_Symmetric::decryptSecretKey($this->passphrase, $p);
            break;
        }

        $msg =\OpenPGP_Message::parse(\OpenPGP::unarmor($contents, 'PGP MESSAGE'));

        $decryptor = new \OpenPGP_Crypt_RSA($privateKey);
        $decrypted = $decryptor->decrypt($msg);

        if ($decrypted) {
            return $decrypted->packets[0]->data;
        }

        return false;
    }
}
