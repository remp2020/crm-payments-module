<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Seeders;

use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Tests\Gateways\TestRecurrentGateway;
use Symfony\Component\Console\Output\OutputInterface;

class TestPaymentGatewaysSeeder implements ISeeder
{
    public function __construct(private PaymentGatewaysRepository $paymentGatewaysRepository)
    {
    }

    public function seed(OutputInterface $output)
    {
        if (!$this->paymentGatewaysRepository->exists(TestRecurrentGateway::GATEWAY_CODE)) {
            $this->paymentGatewaysRepository->add(
                name: TestRecurrentGateway::GATEWAY_CODE,
                code: TestRecurrentGateway::GATEWAY_CODE,
                isRecurrent: true
            );
            $output->writeln('  <comment>* payment gateway <info>' . TestRecurrentGateway::GATEWAY_CODE . '</info> created</comment>');
        } else {
            $output->writeln('  * payment gateway <info>' . TestRecurrentGateway::GATEWAY_CODE . '</info> exists');
        }
    }
}
