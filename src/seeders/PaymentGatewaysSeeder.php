<?php

namespace Crm\PaymentsModule\Seeders;

use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Symfony\Component\Console\Output\OutputInterface;

class PaymentGatewaysSeeder implements ISeeder
{
    private $paymentGatewaysRepository;
    
    public function __construct(PaymentGatewaysRepository $paymentGatewaysRepository)
    {
        $this->paymentGatewaysRepository = $paymentGatewaysRepository;
    }

    public function seed(OutputInterface $output)
    {
        if (!$this->paymentGatewaysRepository->exists('paypal')) {
            $this->paymentGatewaysRepository->add(
                'Paypal',
                'paypal',
                10,
                true,
                false,
                false,
                'Paypal description',
                'https://pbs.twimg.com/profile_images/461536684417380353/yy3lVE1y.jpeg'
            );
            $output->writeln('  <comment>* payment gateway <info>paypal</info> created</comment>');
        } else {
            $output->writeln('  * payment gateway <info>paypal</info> exists');
        }

        if (!$this->paymentGatewaysRepository->exists('paypal_reference')) {
            $this->paymentGatewaysRepository->add(
                'Paypal Reference',
                'paypal_reference',
                15,
                true,
                false,
                false,
                'Recurrent Paypal payments',
                'https://pbs.twimg.com/profile_images/461536684417380353/yy3lVE1y.jpeg',
                false,
                true
            );
            $output->writeln('  <comment>* payment gateway <info>paypal</info> created</comment>');
        } else {
            $output->writeln('  * payment gateway <info>paypal</info> exists');
        }


        if (!$this->paymentGatewaysRepository->exists('cardpay')) {
            $this->paymentGatewaysRepository->add(
                'CardPay',
                'cardpay',
                20,
                true,
                true,
                false,
                'Platobná karta jednorázová platba',
                'http://www.eo.sk/old/components/com_virtuemart/shop_image/product/CardPay___Tatrab_4dbd4484b7bf3.png'
            );
            $output->writeln('  <comment>* payment gateway <info>cardpay</info> created</comment>');
        } else {
            $output->writeln('  * payment gateway <info>cardpay</info> exists');
        }

        if (!$this->paymentGatewaysRepository->exists('comfortpay')) {
            $this->paymentGatewaysRepository->add(
                'ComfortPay',
                'comfortpay',
                21,
                true,
                true,
                false,
                'Platobná karta <br>(Visa, MasterCard, Diners) - automaticky obnovované',
                'http://www.eo.sk/old/components/com_virtuemart/shop_image/product/CardPay___Tatrab_4dbd4484b7bf3.png',
                true,
                true
            );
            $output->writeln('  <comment>* payment gateway <info>comfortpay</info> created</comment>');
        } else {
            $output->writeln('  * payment gateway <info>comfortpay</info> exists');
        }

        if (!$this->paymentGatewaysRepository->exists('tatrapay')) {
            $this->paymentGatewaysRepository->add(
                'TatraPay',
                'tatrapay',
                30,
                true,
                true,
                false,
                'TatraPay',
                'https://www.drupal.org/files/images/tatrapay_small.gif'
            );
            $output->writeln('  <comment>* payment gateway <info>tatrapay</info> created</comment>');
        } else {
            $output->writeln('  * payment gateway <info>tatrapay</info> exists');
        }

        if (!$this->paymentGatewaysRepository->exists('bank_transfer')) {
            $this->paymentGatewaysRepository->add(
                'Bankový prevod',
                'bank_transfer',
                40,
                true,
                true,
                false,
                'Bankový prevod',
                'http://www.zamenej.sk/img/static/prevod-button.jpg'
            );
            $output->writeln('  <comment>* payment gateway <info>bank_transfer</info> created</comment>');
        } else {
            $output->writeln('  * payment gateway <info>bank_transfer</info> exists');
        }

        if (!$this->paymentGatewaysRepository->exists('custom')) {
            $this->paymentGatewaysRepository->add(
                'Custom',
                'custom',
                50,
                true,
                false,
                false,
                'Custom payment',
                ''
            );
            $output->writeln('  <comment>* payment gateway <info>custom</info> created</comment>');
        } else {
            $output->writeln('  * payment gateway <info>custom</info> exists');
        }

        if (!$this->paymentGatewaysRepository->exists('csob')) {
            $this->paymentGatewaysRepository->add(
                'ČSOB',
                'csob',
                22,
                true,
                true,
                false,
                'Platební karta - jednorázová platba',
                'https://platebnibrana.csob.cz/images/brand-pay-csob-cz-96x61.png'
            );
            $output->writeln('  <comment>* payment gateway <info>csob</info> created</comment>');
        } else {
            $output->writeln('  * payment gateway <info>csob</info> exists');
        }

        if (!$this->paymentGatewaysRepository->exists('csob_one_click')) {
            $this->paymentGatewaysRepository->add(
                'ČSOB One Click',
                'csob_one_click',
                23,
                true,
                true,
                false,
                'Platební karta<br>(Visa, MasterCard) - automaticky obnovovány',
                'https://platebnibrana.csob.cz/images/brand-pay-csob-cz-96x61.png',
                false,
                true
            );
            $output->writeln('  <comment>* payment gateway <info>csob_one_click</info> created</comment>');
        } else {
            $output->writeln('  * payment gateway <info>csob_one_click</info> exists');
        }
    }
}
