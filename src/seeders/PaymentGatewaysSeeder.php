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
                true
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
                true
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
                true
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
                true
            );
            $output->writeln('  <comment>* payment gateway <info>bank_transfer</info> created</comment>');
        } else {
            $output->writeln('  * payment gateway <info>bank_transfer</info> exists');
        }

        if (!$this->paymentGatewaysRepository->exists('csob')) {
            $this->paymentGatewaysRepository->add(
                'ČSOB',
                'csob',
                22,
                true
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
                true
            );
            $output->writeln('  <comment>* payment gateway <info>csob_one_click</info> created</comment>');
        } else {
            $output->writeln('  * payment gateway <info>csob_one_click</info> exists');
        }

        if (!$this->paymentGatewaysRepository->exists('gopay')) {
            $this->paymentGatewaysRepository->add(
                'GoPay',
                'gopay',
                24,
                true,
                false
            );
            $output->writeln('  <comment>* payment gateway <info>gopay</info> created</comment>');
        } else {
            $output->writeln('  * payment gateway <info>gopay</info> exists');
        }

        if (!$this->paymentGatewaysRepository->exists('gopay_recurrent')) {
            $this->paymentGatewaysRepository->add(
                'GoPay Recurrent',
                'gopay_recurrent',
                25,
                true,
                true
            );
            $output->writeln('  <comment>* payment gateway <info>gopay_recurrent</info> created</comment>');
        } else {
            $output->writeln('  * payment gateway <info>gopay_recurrent</info> exists');
        }

        if (!$this->paymentGatewaysRepository->exists('free')) {
            $this->paymentGatewaysRepository->add(
                'Free',
                'free',
                10,
                true
            );
            $output->writeln('  <comment>* payment gateway <info>Free</info> created</comment>');
        } else {
            $output->writeln('  * payment gateway <info>Free</info> exists');
        }
    }
}
