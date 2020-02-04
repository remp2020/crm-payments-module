<?php

namespace Crm\PaymentsModule\Seeders;

use Crm\ApplicationModule\Builder\ConfigBuilder;
use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\Config\Repository\ConfigCategoriesRepository;
use Crm\ApplicationModule\Config\Repository\ConfigsRepository;
use Crm\ApplicationModule\Seeders\ISeeder;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigsSeeder implements ISeeder
{
    private $configCategoriesRepository;

    private $configsRepository;

    private $configBuilder;

    private $category;

    public function __construct(
        ConfigCategoriesRepository $configCategoriesRepository,
        ConfigsRepository $configsRepository,
        ConfigBuilder $configBuilder
    ) {
        $this->configCategoriesRepository = $configCategoriesRepository;
        $this->configsRepository = $configsRepository;
        $this->configBuilder = $configBuilder;
    }

    public function seed(OutputInterface $output)
    {
        $categoryName = 'payments.config.category';
        $this->category = $category = $this->configCategoriesRepository->loadByName($categoryName);
        if (!$category) {
            $this->category = $category = $this->configCategoriesRepository->add($categoryName, 'fa fa-credit-card', 300);
            $output->writeln('  <comment>* config category <info>Platby</info> created</comment>');
        } else {
            $output->writeln('  * config category <info>Platby</info> exists');
        }

        $this->addPaymentConfig(
            $output,
            $category,
            'recurrent_charge_before',
            'payments.config.recurrent_charge_before.name',
            null,
            300,
            'payments.config.recurrent_charge_before.description'
        );

        $this->addPaymentConfig(
            $output,
            $category,
            'donation_vat_rate',
            'payments.config.donation_vat_rate.name',
            null,
            800
        );

        $sorting = 1000;

        $this->addPaymentConfig($output, $category, 'tatrapay_mid', 'payments.config.tatrapay_mid.name', 'aoj', $sorting++);
        $this->addPaymentConfig(
            $output,
            $category,
            'tatrapay_sharedsecret',
            'payments.config.tatrapay_sharedsecret.name',
            '',
            $sorting++
        );

        $this->addPaymentConfig($output, $category, 'cardpay_mid', 'payments.config.cardpay_mid.name', '1joa', $sorting++);
        $this->addPaymentConfig(
            $output,
            $category,
            'cardpay_sharedsecret',
            'payments.config.cardpay_sharedsecret.name',
            '',
            $sorting++
        );

        $this->addPaymentConfig($output, $category, 'comfortpay_mid', 'payments.config.comfortpay_mid.name', '5120', $sorting++);
        $this->addPaymentConfig($output, $category, 'comfortpay_ws', 'payments.config.comfortpay_ws.name', '668862', $sorting++);
        $this->addPaymentConfig(
            $output,
            $category,
            'comfortpay_terminalid',
            'payments.config.comfortpay_terminalid.name',
            '',
            $sorting++
        );
        $this->addPaymentConfig(
            $output,
            $category,
            'comfortpay_sharedsecret',
            'payments.config.comfortpay_sharedsecret.name',
            '',
            $sorting++
        );
        $this->addPaymentConfig(
            $output,
            $category,
            'comfortpay_local_cert_path',
            'payments.config.comfortpay_local_cert_path.name',
            '123',
            $sorting++,
            'payments.config.comfortpay_local_passphrase_path.description'
        );
        $this->addPaymentConfig(
            $output,
            $category,
            'comfortpay_local_passphrase_path',
            'payments.config.comfortpay_local_passphrase_path.name',
            '123',
            $sorting++,
            'payments.config.comfortpay_local_passphrase_path.description'
        );
        $this->addPaymentConfig(
            $output,
            $category,
            'comfortpay_tem',
            'payments.config.comfortpay_tem.name',
            'info@info.sk',
            $sorting++,
            'payments.config.comfortpay_tem.description'
        );
        $this->addPaymentConfig(
            $output,
            $category,
            'comfortpay_rem',
            'payments.config.comfortpay_rem.name',
            'info@info.sk',
            $sorting++,
            'payments.config.comfortpay_rem.description'
        );

        $this->addPaymentConfig($output, $category, 'paypal_mode', 'payments.config.paypal_mode.name', 'live', $sorting++);
        $this->addPaymentConfig(
            $output,
            $category,
            'paypal_username',
            'payments.config.paypal_username.name',
            '',
            $sorting++
        );
        $this->addPaymentConfig(
            $output,
            $category,
            'paypal_password',
            'payments.config.paypal_password.name',
            '',
            $sorting++
        );
        $this->addPaymentConfig(
            $output,
            $category,
            'paypal_signature',
            'payments.config.paypal_signature.name',
            '',
            $sorting++
        );
        $this->addPaymentConfig($output, $category, 'paypal_merchant', 'payments.config.paypal_merchant.name', '', $sorting);

        $this->addPaymentConfig(
            $output,
            $category,
            'csob_merchant_id',
            'payments.config.csob_merchant_id.name',
            '',
            $sorting++,
            'payments.config.csob_merchant_id.description'
        );

        $this->addPaymentConfig(
            $output,
            $category,
            'csob_shop_name',
            'payments.config.csob_shop_name.name',
            '',
            $sorting++,
            "payments.config.csob_shop_name.description"
        );

        $this->addPaymentConfig(
            $output,
            $category,
            'csob_bank_public_key_file_path',
            'payments.config.csob_bank_public_key_file_path.name',
            '',
            $sorting++,
            'payments.config.csob_bank_public_key_file_path.description'
        );

        $this->addPaymentConfig(
            $output,
            $category,
            'csob_private_key_file_path',
            'payments.config.csob_private_key_file_path.name',
            '',
            $sorting++,
            'payments.config.csob_private_key_file_path.description'
        );

        $this->addPaymentConfig(
            $output,
            $category,
            'csob_mode',
            'payments.config.csob_mode.name',
            '',
            $sorting++,
            'payments.config.csob_mode.description'
        );

        // recurrent payments

        $this->addPaymentConfig(
            $output,
            $category,
            'recurrent_payment_gateway_fail_delay',
            'payments.config.recurrent_payment_gateway_fail_delay.name',
            'PT1H',
            $sorting++,
            'payments.config.recurrent_payment_gateway_fail_delay.description'
        );

        $this->addPaymentConfig(
            $output,
            $category,
            'recurrent_payment_charges',
            'payments.config.recurrent_payment_charges.name',
            'PT15M, PT6H, PT6H, PT6H, PT6H',
            $sorting++,
            'payments.config.recurrent_payment_charges.description'
        );

        // gopay

        $this->addPaymentConfig(
            $output,
            $category,
            'gopay_go_id',
            'payments.config.gopay_go_id.name',
            '',
            $sorting++,
            null
        );
        
        $this->addPaymentConfig(
            $output,
            $category,
            'gopay_client_id',
            'payments.config.gopay_client_id.name',
            '',
            $sorting++,
            null
        );

        $this->addPaymentConfig(
            $output,
            $category,
            'gopay_client_secret',
            'payments.config.gopay_client_secret.name',
            '',
            $sorting++,
            null
        );

        $this->addPaymentConfig(
            $output,
            $category,
            'gopay_mode',
            'payments.config.gopay_mode.name',
            'true',
            $sorting++,
            null
        );

        $this->addPaymentConfig(
            $output,
            $category,
            'gopay_recurrence_date_to',
            'payments.config.gopay_recurrence_date_to.name',
            '2030-12-30',
            $sorting++,
            null
        );

        $this->addPaymentConfig(
            $output,
            $category,
            'tatrapay_mode',
            'payments.config.tatrapay_mode.name',
            'live',
            $sorting++,
            null
        );

        $this->addPaymentConfig(
            $output,
            $category,
            'cardpay_mode',
            'payments.config.cardpay_mode.name',
            'live',
            $sorting++,
            null
        );

        $this->addPaymentConfig(
            $output,
            $category,
            'comfortpay_mode',
            'payments.config.comfortpay_mode.name',
            'live',
            $sorting++,
            null
        );

        $this->addPaymentConfig(
            $output,
            $category,
            'gopay_eet_enabled',
            'payments.config.gopay_eet_enabled.name',
            0,
            $sorting++,
            null
        );

        $this->addPaymentConfig(
            $output,
            $category,
            'vub_zip_password',
            'payments.config.vub_zip_password.name',
            '',
            $sorting++,
            'payments.config.vub_zip_password.description'
        );

        $this->addPaymentConfig(
            $output,
            $category,
            'tatrabanka_pgp_private_key_path',
            'payments.config.tatrabanka_pgp_private_key_path.name',
            '',
            $sorting++,
            'payments.config.tatrabanka_pgp_private_key_path.description'
        );

        $this->addPaymentConfig(
            $output,
            $category,
            'tatrabanka_pgp_private_key_passphrase',
            'payments.config.tatrabanka_pgp_private_key_passphrase.name',
            '',
            $sorting++,
            'payments.config.tatrabanka_pgp_private_key_passphrase.description'
        );

        $name = 'gopay_eet_enabled';
        $value = 0;
        $config = $this->configsRepository->loadByName($name);
        if (!$config) {
            $this->configBuilder->createNew()
                ->setName($name)
                ->setDisplayName('payments.config.gopay_eet_enabled.name')
                ->setDescription('payments.config.gopay_eet_enabled.description')
                ->setValue($value)
                ->setType(ApplicationConfig::TYPE_BOOLEAN)
                ->setAutoload(false)
                ->setConfigCategory($category)
                ->setSorting($sorting++)
                ->save();
            $output->writeln("  <comment>* config item <info>$name</info> created</comment>");
        } elseif ($config->has_default_value && $config->value !== $value) {
            $this->configsRepository->update($config, ['value' => $value, 'has_default_value' => true]);
            $output->writeln("  <comment>* config item <info>$name</info> updated</comment>");
        } else {
            $output->writeln("  * config item <info>$name</info> exists");
        }

        $name = 'confirmation_mail_host';
        $config = $this->configsRepository->loadByName($name);
        if (!$config) {
            $this->configBuilder->createNew()
                ->setName($name)
                ->setDisplayName('payments.config.confirmation_mail_host.name')
                ->setDescription('payments.config.confirmation_mail_host.description')
                ->setType(ApplicationConfig::TYPE_STRING)
                ->setAutoload(true)
                ->setConfigCategory($category)
                ->setSorting(1000)
                ->save();
            $output->writeln("  <comment>* config item <info>$name</info> created</comment>");
        } else {
            $output->writeln("  * config item <info>$name</info> exists");
        }

        $name = 'confirmation_mail_port';
        $config = $this->configsRepository->loadByName($name);
        if (!$config) {
            $this->configBuilder->createNew()
                ->setName($name)
                ->setDisplayName('payments.config.confirmation_mail_port.name')
                ->setDescription('')
                ->setType(ApplicationConfig::TYPE_STRING)
                ->setAutoload(true)
                ->setConfigCategory($category)
                ->setSorting(1001)
                ->save();
            $output->writeln("  <comment>* config item <info>$name</info> created</comment>");
        } else {
            $output->writeln("  * config item <info>$name</info> exists");
        }

        $name = 'confirmation_mail_username';
        $config = $this->configsRepository->loadByName($name);
        if (!$config) {
            $this->configBuilder->createNew()
                ->setName($name)
                ->setDisplayName('payments.config.confirmation_mail_username.name')
                ->setDescription('')
                ->setType(ApplicationConfig::TYPE_STRING)
                ->setAutoload(true)
                ->setConfigCategory($category)
                ->setSorting(1002)
                ->save();
            $output->writeln("  <comment>* config item <info>$name</info> created</comment>");
        } else {
            $output->writeln("  * config item <info>$name</info> exists");
        }

        $name = 'confirmation_mail_password';
        $config = $this->configsRepository->loadByName($name);
        if (!$config) {
            $this->configBuilder->createNew()
                ->setName($name)
                ->setDisplayName('payments.config.confirmation_mail_password.name')
                ->setDescription('')
                ->setType(ApplicationConfig::TYPE_PASSWORD)
                ->setAutoload(true)
                ->setConfigCategory($category)
                ->setSorting(1003)
                ->save();
            $output->writeln("  <comment>* config item <info>$name</info> created</comment>");
        } else {
            $output->writeln("  * config item <info>$name</info> exists");
        }

        $name = 'confirmation_mail_processed_folder';
        $config = $this->configsRepository->loadByName($name);
        if (!$config) {
            $this->configBuilder->createNew()
                ->setName($name)
                ->setDisplayName('payments.config.confirmation_mail_processed_folder.name')
                ->setDescription('payments.config.confirmation_mail_processed_folder.description')
                ->setType(ApplicationConfig::TYPE_STRING)
                ->setAutoload(true)
                ->setConfigCategory($category)
                ->setSorting(1004)
                ->save();
            $output->writeln("  <comment>* config item <info>$name</info> created</comment>");
        } else {
            $output->writeln("  * config item <info>$name</info> exists");
        }
    }

    private function addPaymentConfig(OutputInterface $output, $category, $name, $displayName, $value, $sorting, $description = null)
    {
        $config = $this->configsRepository->loadByName($name);
        if (!$config) {
            $this->configBuilder->createNew()
                ->setName($name)
                ->setDisplayName($displayName)
                ->setDescription($description)
                ->setValue($value)
                ->setType(ApplicationConfig::TYPE_STRING)
                ->setAutoload(true)
                ->setConfigCategory($category)
                ->setSorting($sorting)
                ->save();
            $output->writeln("  <comment>* config item <info>$name</info> created</comment>");
        } else {
            $output->writeln("  * config item <info>$name</info> exists");

            if ($config->has_default_value && $config->value !== $value) {
                $this->configsRepository->update($config, ['value' => $value, 'has_default_value' => true]);
                $output->writeln("  <comment>* config item <info>$name</info> updated</comment>");
            }

            if ($config->category->name != $this->category->name) {
                $this->configsRepository->update($config, [
                    'config_category_id' => $this->category->id
                ]);
                $output->writeln("  <comment>* config item <info>$name</info> updated</comment>");
            }
        }
    }
}
