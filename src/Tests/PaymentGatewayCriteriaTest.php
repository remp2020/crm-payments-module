<?php

namespace Crm\PaymentsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\PaymentsModule\Gateways\BankTransfer;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Scenarios\PaymentGatewayCriteria;
use Crm\PaymentsModule\Seeders\PaymentGatewaysSeeder;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\UsersRepository;

class PaymentGatewayCriteriaTest extends DatabaseTestCase
{
    /** @var PaymentsRepository */
    private $paymentsRepository;

    /** @var UsersRepository */
    private $usersRepository;

    /** @var PaymentGatewayCriteria */
    private $paymentGatewayCriteria;

    public function setUp(): void
    {
        parent::setUp();
        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->paymentsRepository = $this->getRepository(PaymentsRepository::class);
        $this->paymentGatewayCriteria = $this->inject(PaymentGatewayCriteria::class);
    }

    protected function requiredRepositories(): array
    {
        return [
            PaymentsRepository::class,
            UsersRepository::class,
            PaymentGatewaysRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            PaymentGatewaysSeeder::class,
        ];
    }

    public function testCriteria(): void
    {
        [$userRow, $paymentRow] = $this->prepareData('user1@example.com', 'free');
        $q = $this->paymentsRepository->getTable()->where('payments.id = ?', $paymentRow->id);
        $this->assertTrue($this->paymentGatewayCriteria->addConditions(
            $q,
            [PaymentGatewayCriteria::KEY => (object)['selection' => [BankTransfer::GATEWAY_CODE]]],
            $paymentRow
        ));
        $this->assertFalse($q->fetch());

        [$userRow, $paymentRow] = $this->prepareData('user2@example.com', BankTransfer::GATEWAY_CODE);
        $q = $this->paymentsRepository->getTable()->where('payments.id = ?', $paymentRow->id);
        $this->assertTrue($this->paymentGatewayCriteria->addConditions(
            $q,
            [PaymentGatewayCriteria::KEY => (object)['selection' => [BankTransfer::GATEWAY_CODE]]],
            $paymentRow
        ));
        $this->assertNotFalse($q->fetch());
    }

    private function prepareData(string $userEmail, string $paymentGatewayCode): array
    {
        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $userRow = $userManager->addNewUser($userEmail);

        /** @var PaymentGatewaysRepository $paymentGatewaysRepository */
        $paymentGatewaysRepository = $this->getRepository(PaymentGatewaysRepository::class);
        $paymentGatewayRow = $paymentGatewaysRepository->findBy('code', $paymentGatewayCode);

        $paymentRow = $this->paymentsRepository->add(
            null,
            $paymentGatewayRow,
            $userRow,
            new PaymentItemContainer(),
            null,
            1
        );

        $paymentRow = $this->paymentsRepository->updateStatus($paymentRow, PaymentsRepository::STATUS_PAID);

        return [$userRow, $paymentRow];
    }
}
