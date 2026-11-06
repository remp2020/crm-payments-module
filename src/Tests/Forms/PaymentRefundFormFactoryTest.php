<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Tests\Forms;

use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\UI\Form;
use Crm\PaymentsModule\DataProviders\PaymentRefundFormDataProviderInterface;
use Crm\PaymentsModule\Forms\PaymentRefundFormFactory;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Tests\PaymentsTestCase;
use Nette\Utils\ArrayHash;

class PaymentRefundFormFactoryTest extends PaymentsTestCase
{
    public function testProvidersRunBeforePaymentStatusChanges(): void
    {
        $payment = $this->createPayment('5157000002');
        $this->paymentsRepository->updateStatus($payment, PaymentStatusEnum::Paid->value, false);

        $provider = new class ($this->paymentsRepository) implements PaymentRefundFormDataProviderInterface {
            public ?string $paymentStatusOnFormSucceeded = null;

            public function __construct(private readonly PaymentsRepository $paymentsRepository)
            {
            }

            public function provide(array $params): Form
            {
                return $params['form'];
            }

            public function formSucceeded(Form $form, ArrayHash $values): array
            {
                $payment = $this->paymentsRepository->find($values[PaymentRefundFormFactory::PAYMENT_ID_KEY]);
                $this->paymentStatusOnFormSucceeded = $payment->status;
                return [$form, $values];
            }
        };
        $this->inject(DataProviderManager::class)->registerDataProvider(
            PaymentRefundFormDataProviderInterface::PATH,
            $provider,
        );

        $formFactory = $this->inject(PaymentRefundFormFactory::class);
        $form = $formFactory->create($payment->id);
        $formFactory->formSucceeded($form, ArrayHash::from([
            PaymentRefundFormFactory::PAYMENT_ID_KEY => $payment->id,
            PaymentRefundFormFactory::NEW_PAYMENT_STATUS => PaymentStatusEnum::Refund->value,
        ]));

        $this->assertSame(PaymentStatusEnum::Paid->value, $provider->paymentStatusOnFormSucceeded);
        $this->assertSame(PaymentStatusEnum::Refund->value, $this->paymentsRepository->find($payment->id)->status);
    }
}
