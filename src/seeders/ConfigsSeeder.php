<?php

namespace Crm\PaymentsModule\Seeders;

use Crm\ApplicationModule\Builder\ConfigBuilder;
use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\Config\Repository\ConfigCategoriesRepository;
use Crm\ApplicationModule\Config\Repository\ConfigsRepository;
use Crm\ApplicationModule\Seeders\ConfigsTrait;
use Crm\ApplicationModule\Seeders\ISeeder;
use Nette\Database\Connection;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigsSeeder implements ISeeder
{
    use ConfigsTrait;

    private $configCategoriesRepository;

    private $configsRepository;

    private $configBuilder;

    private $database;

    public function __construct(
        ConfigCategoriesRepository $configCategoriesRepository,
        ConfigsRepository $configsRepository,
        ConfigBuilder $configBuilder,
        Connection $database
    ) {
        $this->configCategoriesRepository = $configCategoriesRepository;
        $this->configsRepository = $configsRepository;
        $this->configBuilder = $configBuilder;
        $this->database = $database;
    }

    public function seed(OutputInterface $output)
    {
        $categoryName = 'payments.config.category';
        $category = $this->configCategoriesRepository->loadByName($categoryName);
        if (!$category) {
            $category = $this->configCategoriesRepository->add($categoryName, 'fa fa-credit-card', 300);
            $output->writeln('  <comment>* config category <info>Platby</info> created</comment>');
        } else {
            $output->writeln('  * config category <info>Platby</info> exists');
        }

        $this->addConfig(
            $output,
            $category,
            'recurrent_charge_before',
            ApplicationConfig::TYPE_STRING,
            'payments.config.recurrent_charge_before.name',
            'payments.config.recurrent_charge_before.description',
            '',
            300
        );

        $this->addConfig(
            $output,
            $category,
            'donation_vat_rate',
            ApplicationConfig::TYPE_STRING,
            'payments.config.donation_vat_rate.name',
            null,
            null,
            800
        );

        $sorting = 1000;

        // TATRAPAY

        $this->addConfig(
            $output,
            $category,
            'tatrapay_mid',
            ApplicationConfig::TYPE_STRING,
            'payments.config.tatrapay_mid.name',
            null,
            'aoj',
            $sorting++
        );

        $this->addConfig(
            $output,
            $category,
            'tatrapay_sharedsecret',
            ApplicationConfig::TYPE_STRING,
            'payments.config.tatrapay_sharedsecret.name',
            null,
            '',
            $sorting++
        );

        $this->addConfig(
            $output,
            $category,
            'tatrapay_mode',
            ApplicationConfig::TYPE_STRING,
            'payments.config.tatrapay_mode.name',
            null,
            'live',
            $sorting++
        );

        // CARDPAY

        $this->addConfig(
            $output,
            $category,
            'cardpay_mid',
            ApplicationConfig::TYPE_STRING,
            'payments.config.cardpay_mid.name',
            null,
            '1joa',
            $sorting++
        );

        $this->addConfig(
            $output,
            $category,
            'cardpay_sharedsecret',
            ApplicationConfig::TYPE_STRING,
            'payments.config.cardpay_sharedsecret.name',
            null,
            '',
            $sorting++
        );

        $this->addConfig(
            $output,
            $category,
            'cardpay_mode',
            ApplicationConfig::TYPE_STRING,
            'payments.config.cardpay_mode.name',
            null,
            'live',
            $sorting++
        );

        // COMFORTPAY

        $this->addConfig(
            $output,
            $category,
            'comfortpay_mid',
            ApplicationConfig::TYPE_STRING,
            'payments.config.comfortpay_mid.name',
            null,
            '5120',
            $sorting++
        );

        $this->addConfig(
            $output,
            $category,
            'comfortpay_ws',
            ApplicationConfig::TYPE_STRING,
            'payments.config.comfortpay_ws.name',
            null,
            '668862',
            $sorting++
        );

        $this->addConfig(
            $output,
            $category,
            'comfortpay_terminalid',
            ApplicationConfig::TYPE_STRING,
            'payments.config.comfortpay_terminalid.name',
            null,
            '',
            $sorting++
        );

        $this->addConfig(
            $output,
            $category,
            'comfortpay_sharedsecret',
            ApplicationConfig::TYPE_STRING,
            'payments.config.comfortpay_sharedsecret.name',
            null,
            '',
            $sorting++
        );

        $this->addConfig(
            $output,
            $category,
            'comfortpay_local_cert_path',
            ApplicationConfig::TYPE_STRING,
            'payments.config.comfortpay_local_cert_path.name',
            'payments.config.comfortpay_local_passphrase_path.description',
            '',
            $sorting++
        );

        $this->addConfig(
            $output,
            $category,
            'comfortpay_local_passphrase_path',
            ApplicationConfig::TYPE_STRING,
            'payments.config.comfortpay_local_passphrase_path.name',
            'payments.config.comfortpay_local_passphrase_path.description',
            '',
            $sorting++
        );

        $this->addConfig(
            $output,
            $category,
            'comfortpay_tem',
            ApplicationConfig::TYPE_STRING,
            'payments.config.comfortpay_tem.name',
            'payments.config.comfortpay_tem.description',
            'admin@example.com',
            $sorting++
        );

        $this->addConfig(
            $output,
            $category,
            'comfortpay_rem',
            ApplicationConfig::TYPE_STRING,
            'payments.config.comfortpay_rem.name',
            'payments.config.comfortpay_rem.description',
            'admin@example.com',
            $sorting++
        );

        $this->addConfig(
            $output,
            $category,
            'comfortpay_mode',
            ApplicationConfig::TYPE_STRING,
            'payments.config.comfortpay_mode.name',
            null,
            'live',
            $sorting++
        );

        // PAYPAL

        $this->addConfig(
            $output,
            $category,
            'paypal_mode',
            ApplicationConfig::TYPE_STRING,
            'payments.config.paypal_mode.name',
            null,
            'live',
            $sorting++
        );

        $this->addConfig(
            $output,
            $category,
            'paypal_username',
            ApplicationConfig::TYPE_STRING,
            'payments.config.paypal_username.name',
            null,
            '',
            $sorting++
        );

        $this->addConfig(
            $output,
            $category,
            'paypal_password',
            ApplicationConfig::TYPE_STRING,
            'payments.config.paypal_password.name',
            null,
            '',
            $sorting++
        );

        $this->addConfig(
            $output,
            $category,
            'paypal_signature',
            ApplicationConfig::TYPE_STRING,
            'payments.config.paypal_signature.name',
            null,
            '',
            $sorting++
        );

        $this->addConfig(
            $output,
            $category,
            'paypal_merchant',
            ApplicationConfig::TYPE_STRING,
            'payments.config.paypal_merchant.name',
            null,
            '',
            $sorting++
        );

        $this->addConfig(
            $output,
            $category,
            'csob_merchant_id',
            ApplicationConfig::TYPE_STRING,
            'payments.config.csob_merchant_id.name',
            'payments.config.csob_merchant_id.description',
            '',
            $sorting++
        );

        $this->addConfig(
            $output,
            $category,
            'csob_shop_name',
            ApplicationConfig::TYPE_STRING,
            'payments.config.csob_shop_name.name',
            'payments.config.csob_shop_name.description',
            '',
            $sorting++
        );

        $this->addConfig(
            $output,
            $category,
            'csob_bank_public_key_file_path',
            ApplicationConfig::TYPE_STRING,
            'payments.config.csob_bank_public_key_file_path.name',
            'payments.config.csob_bank_public_key_file_path.description',
            '',
            $sorting++
        );

        $this->addConfig(
            $output,
            $category,
            'csob_private_key_file_path',
            ApplicationConfig::TYPE_STRING,
            'payments.config.csob_private_key_file_path.name',
            'payments.config.csob_private_key_file_path.description',
            '',
            $sorting++
        );

        $this->addConfig(
            $output,
            $category,
            'csob_mode',
            ApplicationConfig::TYPE_STRING,
            'payments.config.csob_mode.name',
            'payments.config.csob_mode.description',
            '',
            $sorting++
        );

        // recurrent payments

        $this->addConfig(
            $output,
            $category,
            'recurrent_payment_gateway_fail_delay',
            ApplicationConfig::TYPE_STRING,
            'payments.config.recurrent_payment_gateway_fail_delay.name',
            'payments.config.recurrent_payment_gateway_fail_delay.description',
            'PT1H',
            $sorting++
        );

        $this->addConfig(
            $output,
            $category,
            'recurrent_payment_charges',
            ApplicationConfig::TYPE_STRING,
            'payments.config.recurrent_payment_charges.name',
            'payments.config.recurrent_payment_charges.description',
            'PT15M, PT6H, PT6H, PT6H, PT6H',
            $sorting++
        );

        // GOPAY

        $this->addConfig(
            $output,
            $category,
            'gopay_go_id',
            ApplicationConfig::TYPE_STRING,
            'payments.config.gopay_go_id.name',
            null,
            null,
            $sorting++
        );

        $this->addConfig(
            $output,
            $category,
            'gopay_client_id',
            ApplicationConfig::TYPE_STRING,
            'payments.config.gopay_client_id.name',
            null,
            null,
            $sorting++
        );

        $this->addConfig(
            $output,
            $category,
            'gopay_client_secret',
            ApplicationConfig::TYPE_STRING,
            'payments.config.gopay_client_secret.name',
            null,
            null,
            $sorting++
        );

        $this->addConfig(
            $output,
            $category,
            'gopay_mode',
            ApplicationConfig::TYPE_STRING,
            'payments.config.gopay_mode.name',
            null,
            null,
            $sorting++
        );

        $this->addConfig(
            $output,
            $category,
            'gopay_recurrence_date_to',
            ApplicationConfig::TYPE_STRING,
            'payments.config.gopay_recurrence_date_to.name',
            null,
            '2030-12-30',
            $sorting++
        );

        $this->addConfig(
            $output,
            $category,
            'gopay_eet_enabled',
            ApplicationConfig::TYPE_STRING,
            'payments.config.gopay_eet_enabled.name',
            'payments.config.gopay_eet_enabled.description',
            0,
            $sorting++
        );

        $confirmationCategory = $this->configCategoriesRepository->loadByName('payments.config.category_confirmation');
        if (!$confirmationCategory) {
            $confirmationCategory = $this->configCategoriesRepository->add('payments.config.category_confirmation', 'fa fa-check-double', 1600);
            $output->writeln('  <comment>* config category <info>Potvrdzovacie e-maily</info> created</comment>');
        } else {
            $output->writeln('  * config category <info>Potvrdzovacie e-maily</info> exists');
        }

        // default values for confirmation configs in case previous version of configs was already used
        $host = $this->configsRepository->loadByName('confirmation_mail_host')->value ?? '';
        $port = $this->configsRepository->loadByName('confirmation_mail_port')->value ?? '';
        $username = $this->configsRepository->loadByName('confirmation_mail_username')->value ?? '';
        $password = $this->configsRepository->loadByName('confirmation_mail_password')->value ?? '';
        $processedFolder = $this->configsRepository->loadByName('confirmation_mail_processed_folder')->value ?? '';

        $this->addConfig(
            $output,
            $confirmationCategory,
            'tb_simple_confirmation_host',
            ApplicationConfig::TYPE_STRING,
            'payments.config.tb_simple_confirmation_host.name',
            'payments.config.tb_simple_confirmation_host.description',
            $host,
            101
        );

        $this->addConfig(
            $output,
            $confirmationCategory,
            'tb_simple_confirmation_port',
            ApplicationConfig::TYPE_STRING,
            'payments.config.tb_simple_confirmation_port.name',
            'payments.config.tb_simple_confirmation_port.description',
            $port,
            102
        );

        $this->addConfig(
            $output,
            $confirmationCategory,
            'tb_simple_confirmation_username',
            ApplicationConfig::TYPE_STRING,
            'payments.config.tb_simple_confirmation_username.name',
            'payments.config.tb_simple_confirmation_username.description',
            $username,
            103
        );

        $this->addConfig(
            $output,
            $confirmationCategory,
            'tb_simple_confirmation_password',
            ApplicationConfig::TYPE_STRING,
            'payments.config.tb_simple_confirmation_password.name',
            'payments.config.tb_simple_confirmation_password.description',
            $password,
            104
        );

        $this->addConfig(
            $output,
            $confirmationCategory,
            'tb_simple_confirmation_processed_folder',
            ApplicationConfig::TYPE_STRING,
            'payments.config.tb_simple_confirmation_processed_folder.name',
            'payments.config.tb_simple_confirmation_processed_folder.description',
            $processedFolder,
            105
        );

        $this->addConfig(
            $output,
            $confirmationCategory,
            'tb_confirmation_host',
            ApplicationConfig::TYPE_STRING,
            'payments.config.tb_confirmation_host.name',
            'payments.config.tb_confirmation_host.description',
            $host,
            201
        );

        $this->addConfig(
            $output,
            $confirmationCategory,
            'tb_confirmation_port',
            ApplicationConfig::TYPE_STRING,
            'payments.config.tb_confirmation_port.name',
            'payments.config.tb_confirmation_port.description',
            $port,
            202
        );

        $this->addConfig(
            $output,
            $confirmationCategory,
            'tb_confirmation_username',
            ApplicationConfig::TYPE_STRING,
            'payments.config.tb_confirmation_username.name',
            'payments.config.tb_confirmation_username.description',
            $username,
            203
        );

        $this->addConfig(
            $output,
            $confirmationCategory,
            'tb_confirmation_password',
            ApplicationConfig::TYPE_STRING,
            'payments.config.tb_confirmation_password.name',
            'payments.config.tb_confirmation_password.description',
            $password,
            204
        );

        $this->addConfig(
            $output,
            $confirmationCategory,
            'tb_confirmation_processed_folder',
            ApplicationConfig::TYPE_STRING,
            'payments.config.tb_confirmation_processed_folder.name',
            'payments.config.tb_confirmation_processed_folder.description',
            $processedFolder,
            205
        );

        $this->addConfig(
            $output,
            $confirmationCategory,
            'csob_confirmation_host',
            ApplicationConfig::TYPE_STRING,
            'payments.config.csob_confirmation_host.name',
            'payments.config.csob_confirmation_host.description',
            $host,
            301
        );

        $this->addConfig(
            $output,
            $confirmationCategory,
            'csob_confirmation_port',
            ApplicationConfig::TYPE_STRING,
            'payments.config.csob_confirmation_port.name',
            'payments.config.csob_confirmation_port.description',
            $port,
            302
        );

        $this->addConfig(
            $output,
            $confirmationCategory,
            'csob_confirmation_username',
            ApplicationConfig::TYPE_STRING,
            'payments.config.csob_confirmation_username.name',
            'payments.config.csob_confirmation_username.description',
            $username,
            303
        );

        $this->addConfig(
            $output,
            $confirmationCategory,
            'csob_confirmation_password',
            ApplicationConfig::TYPE_STRING,
            'payments.config.csob_confirmation_password.name',
            'payments.config.csob_confirmation_password.description',
            $password,
            304
        );

        $this->addConfig(
            $output,
            $confirmationCategory,
            'csob_confirmation_processed_folder',
            ApplicationConfig::TYPE_STRING,
            'payments.config.csob_confirmation_processed_folder.name',
            'payments.config.csob_confirmation_processed_folder.description',
            $processedFolder,
            305
        );

        $this->addConfig(
            $output,
            $confirmationCategory,
            'sk_csob_confirmation_host',
            ApplicationConfig::TYPE_STRING,
            'payments.config.sk_csob_confirmation_host.name',
            'payments.config.sk_csob_confirmation_host.description',
            $host,
            401
        );

        $this->addConfig(
            $output,
            $confirmationCategory,
            'sk_csob_confirmation_port',
            ApplicationConfig::TYPE_STRING,
            'payments.config.sk_csob_confirmation_port.name',
            'payments.config.sk_csob_confirmation_port.description',
            $port,
            402
        );

        $this->addConfig(
            $output,
            $confirmationCategory,
            'sk_csob_confirmation_username',
            ApplicationConfig::TYPE_STRING,
            'payments.config.sk_csob_confirmation_username.name',
            'payments.config.sk_csob_confirmation_username.description',
            $username,
            403
        );

        $this->addConfig(
            $output,
            $confirmationCategory,
            'sk_csob_confirmation_password',
            ApplicationConfig::TYPE_STRING,
            'payments.config.sk_csob_confirmation_password.name',
            'payments.config.sk_csob_confirmation_password.description',
            $password,
            404
        );

        $this->addConfig(
            $output,
            $confirmationCategory,
            'sk_csob_confirmation_processed_folder',
            ApplicationConfig::TYPE_STRING,
            'payments.config.sk_csob_confirmation_processed_folder.name',
            'payments.config.sk_csob_confirmation_processed_folder.description',
            $processedFolder,
            405
        );

        $this->addConfig(
            $output,
            $confirmationCategory,
            'tbs_confirmation_host',
            ApplicationConfig::TYPE_STRING,
            'payments.config.tbs_confirmation_host.name',
            'payments.config.tbs_confirmation_host.description',
            $host,
            501
        );

        $this->addConfig(
            $output,
            $confirmationCategory,
            'tbs_confirmation_port',
            ApplicationConfig::TYPE_STRING,
            'payments.config.tbs_confirmation_port.name',
            'payments.config.tbs_confirmation_port.description',
            $port,
            502
        );

        $this->addConfig(
            $output,
            $confirmationCategory,
            'tbs_confirmation_username',
            ApplicationConfig::TYPE_STRING,
            'payments.config.tbs_confirmation_username.name',
            'payments.config.tbs_confirmation_username.description',
            $username,
            503
        );

        $this->addConfig(
            $output,
            $confirmationCategory,
            'tbs_confirmation_password',
            ApplicationConfig::TYPE_STRING,
            'payments.config.tbs_confirmation_password.name',
            'payments.config.tbs_confirmation_password.description',
            $password,
            504
        );

        $this->addConfig(
            $output,
            $confirmationCategory,
            'tbs_confirmation_processed_folder',
            ApplicationConfig::TYPE_STRING,
            'payments.config.tbs_confirmation_processed_folder.name',
            'payments.config.tbs_confirmation_processed_folder.description',
            $processedFolder,
            505
        );

        $this->addConfig(
            $output,
            $confirmationCategory,
            'tatrabanka_pgp_private_key_path',
            ApplicationConfig::TYPE_STRING,
            'payments.config.tatrabanka_pgp_private_key_path.name',
            'payments.config.tatrabanka_pgp_private_key_path.description',
            null,
            506
        );

        $this->addConfig(
            $output,
            $confirmationCategory,
            'tatrabanka_pgp_private_key_passphrase',
            ApplicationConfig::TYPE_STRING,
            'payments.config.tatrabanka_pgp_private_key_passphrase.name',
            'payments.config.tatrabanka_pgp_private_key_passphrase.description',
            null,
            507
        );

        // moving configs to different category if they already existed
        $config = $this->configsRepository->loadByName('tatrabanka_pgp_private_key_path');
        $this->configsRepository->update($config, [
            'config_category_id' => $confirmationCategory->id,
            'sorting' => 506,
        ]);

        $config = $this->configsRepository->loadByName('tatrabanka_pgp_private_key_passphrase');
        $this->configsRepository->update($config, [
            'config_category_id' => $confirmationCategory->id,
            'sorting' => 507,
        ]);
    }
}
