<?php

namespace Crm\PaymentsModule\Components\PaymentRefund;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\ApplicationModule\UI\Form;
use Crm\PaymentsModule\DataProviders\PaymentRefundFormDataProviderInterface;
use Crm\PaymentsModule\Forms\PaymentRefundFormFactory;
use Crm\PaymentsModule\Models\GatewayFactory;
use Crm\PaymentsModule\Models\Gateways\RefundStatusEnum;
use Crm\PaymentsModule\Models\Gateways\RefundableInterface;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\RefundPaymentProcessor;
use Crm\PaymentsModule\Repositories\PaymentMetaRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\ArrayHash;

class InstantRefundWidget extends BaseLazyWidget implements PaymentRefundFormDataProviderInterface
{
    private $templateName = 'instant_refund_widget.latte';

    public function __construct(
        LazyWidgetManager $widgetManager,
        private readonly GatewayFactory $gatewayFactory,
        private readonly PaymentsRepository $paymentsRepository,
        private readonly RefundPaymentProcessor $refundPaymentProcessor,
        private readonly PaymentMetaRepository $paymentMetaRepository,
    ) {
        parent::__construct($widgetManager);
    }


    public function provide(array $params): Form
    {
        /** @var Form $form */
        $form = $params['form'];

        /** @var ActiveRow $payment */
        $payment = $params['payment'];
        if ($payment->status !== PaymentStatusEnum::Paid->value) {
            return $form;
        }

        $gateway = $this->gatewayFactory->getGateway($payment->payment_gateway->code);
        if (!$gateway instanceof RefundableInterface) {
            return $form;
        }

        $form->addCheckbox('instant_refund', 'payments.admin.payment_refund.instant_refund_widget.instant_refund.label');
        $form->setDefaults(['instant_refund' => true]);

        return $form;
    }

    public function render(array $params)
    {
        /** @var Form $form */
        $form = $params['form'];

        if (!$form->getComponent('instant_refund', false)) {
            return;
        }

        $this->template->form = $form;
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }

    public function formSucceeded(Form $form, ArrayHash $values): array
    {
        if (!isset($values['instant_refund']) || $values['instant_refund'] !== true) {
            return [$form, $values];
        }

        $paymentId = $values[PaymentRefundFormFactory::PAYMENT_ID_KEY];
        $payment = $this->paymentsRepository->find($paymentId);
        if (!$payment) {
            throw new \RuntimeException("Unable to refund payment, payment '{$paymentId}' doesn't exists.");
        }

        $gateway = $this->gatewayFactory->getGateway($payment->payment_gateway->code);
        if (!$gateway instanceof RefundableInterface) {
            throw new \RuntimeException("Unable to refund payment, gateway '{$payment->payment_gateway->code}' doesn't support refunds.");
        }

        $result = $gateway->refund($payment, $payment->amount);
        if ($result === RefundStatusEnum::Failure) {
            $form->addError('payments.admin.payment_refund.instant_refund_widget.refund_error');
        }
        $this->refundPaymentProcessor->processRefundedPayment($payment, $payment->amount);
        return [$form, $values];
    }
}
