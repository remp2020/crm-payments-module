<?php

namespace Crm\PaymentsModule\Seeders;

use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\PaymentsModule\Models\Gateways\BankTransfer;
use Crm\PaymentsModule\Models\Gateways\CardPayAuthorization;
use Crm\PaymentsModule\Models\Gateways\Cardpay;
use Crm\PaymentsModule\Models\Gateways\Comfortpay;
use Crm\PaymentsModule\Models\Gateways\Csob;
use Crm\PaymentsModule\Models\Gateways\CsobOneClick;
use Crm\PaymentsModule\Models\Gateways\Free;
use Crm\PaymentsModule\Models\Gateways\Paypal;
use Crm\PaymentsModule\Models\Gateways\PaypalReference;
use Crm\PaymentsModule\Models\Gateways\Tatrapay;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
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
        if (!$this->paymentGatewaysRepository->exists(Paypal::GATEWAY_CODE)) {
            $this->paymentGatewaysRepository->add(
                'Paypal',
                Paypal::GATEWAY_CODE,
                10,
                true,
            );
            $output->writeln('  <comment>* payment gateway <info>paypal</info> created</comment>');
        } else {
            $output->writeln('  * payment gateway <info>paypal</info> exists');
        }

        if (!$this->paymentGatewaysRepository->exists(PaypalReference::GATEWAY_CODE)) {
            $this->paymentGatewaysRepository->add(
                'Paypal Reference',
                PaypalReference::GATEWAY_CODE,
                15,
                true,
                true,
            );
            $output->writeln('  <comment>* payment gateway <info>paypal</info> created</comment>');
        } else {
            $output->writeln('  * payment gateway <info>paypal</info> exists');
        }


        if (!$this->paymentGatewaysRepository->exists(Cardpay::GATEWAY_CODE)) {
            $this->paymentGatewaysRepository->add(
                'CardPay',
                Cardpay::GATEWAY_CODE,
                20,
                true,
            );
            $output->writeln('  <comment>* payment gateway <info>cardpay</info> created</comment>');
        } else {
            $output->writeln('  * payment gateway <info>cardpay</info> exists');
        }

        if (!$this->paymentGatewaysRepository->exists(Comfortpay::GATEWAY_CODE)) {
            $this->paymentGatewaysRepository->add(
                'ComfortPay',
                Comfortpay::GATEWAY_CODE,
                21,
                true,
                true,
            );
            $output->writeln('  <comment>* payment gateway <info>comfortpay</info> created</comment>');
        } else {
            $output->writeln('  * payment gateway <info>comfortpay</info> exists');
        }

        if (!$this->paymentGatewaysRepository->exists(Tatrapay::GATEWAY_CODE)) {
            $this->paymentGatewaysRepository->add(
                'TatraPay',
                Tatrapay::GATEWAY_CODE,
                30,
                true,
            );
            $output->writeln('  <comment>* payment gateway <info>tatrapay</info> created</comment>');
        } else {
            $output->writeln('  * payment gateway <info>tatrapay</info> exists');
        }

        if (!$this->paymentGatewaysRepository->exists(BankTransfer::GATEWAY_CODE)) {
            $this->paymentGatewaysRepository->add(
                'Bankový prevod',
                BankTransfer::GATEWAY_CODE,
                40,
                true,
            );
            $output->writeln('  <comment>* payment gateway <info>bank_transfer</info> created</comment>');
        } else {
            $output->writeln('  * payment gateway <info>bank_transfer</info> exists');
        }

        if (!$this->paymentGatewaysRepository->exists(Csob::GATEWAY_CODE)) {
            $this->paymentGatewaysRepository->add(
                'ČSOB',
                Csob::GATEWAY_CODE,
                22,
                true,
            );
            $output->writeln('  <comment>* payment gateway <info>csob</info> created</comment>');
        } else {
            $output->writeln('  * payment gateway <info>csob</info> exists');
        }

        if (!$this->paymentGatewaysRepository->exists(CsobOneClick::GATEWAY_CODE)) {
            $this->paymentGatewaysRepository->add(
                'ČSOB One Click',
                CsobOneClick::GATEWAY_CODE,
                23,
                true,
                true,
            );
            $output->writeln('  <comment>* payment gateway <info>csob_one_click</info> created</comment>');
        } else {
            $output->writeln('  * payment gateway <info>csob_one_click</info> exists');
        }
        if (!$this->paymentGatewaysRepository->exists(Free::GATEWAY_CODE)) {
            $this->paymentGatewaysRepository->add(
                'Free',
                Free::GATEWAY_CODE,
                10,
                true,
            );
            $output->writeln('  <comment>* payment gateway <info>Free</info> created</comment>');
        } else {
            $output->writeln('  * payment gateway <info>Free</info> exists');
        }

        if (!$this->paymentGatewaysRepository->exists(CardPayAuthorization::GATEWAY_CODE)) {
            $this->paymentGatewaysRepository->add(
                'CardPay Authorization',
                CardPayAuthorization::GATEWAY_CODE,
                25,
                false,
            );
            $output->writeln('  <comment>* payment gateway <info>CardPay Authorization</info> created</comment>');
        } else {
            $output->writeln('  * payment gateway <info>CardPay Authorization</info> exists');
        }
    }
}
