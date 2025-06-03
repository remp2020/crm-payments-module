<?php

namespace Crm\PaymentsModule\Components\PaymentRefund;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\ApplicationModule\UI\Form;
use Crm\PaymentsModule\DataProviders\PaymentRefundFormDataProviderInterface;
use Crm\PaymentsModule\Forms\PaymentRefundFormFactory;
use Crm\PaymentsModule\Models\GatewayFactory;
use Crm\PaymentsModule\Models\Gateways\RefundStatusEnum;
use Crm\PaymentsModule\Models\Gateways\RefundTypeEnum;
use Crm\PaymentsModule\Models\Gateways\RefundableInterface;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\RefundPaymentProcessor;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\ArrayHash;
use RuntimeException;

class InstantRefundWidget extends BaseLazyWidget implements PaymentRefundFormDataProviderInterface
{
    private string $templateName = 'instant_refund_widget.latte';

    public function __construct(
        LazyWidgetManager $widgetManager,
        private readonly GatewayFactory $gatewayFactory,
        private readonly PaymentsRepository $paymentsRepository,
        private readonly RefundPaymentProcessor $refundPaymentProcessor,
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

        $instantRefund = $form->addCheckbox(
            'instant_refund',
            'payments.admin.payment_refund.instant_refund_widget.instant_refund.label',
        )->setDefaultValue(true);

        $instantRefund->addCondition($form::Equal, true)
            ->toggle('refund-options');

        $refundTypeOptions = [
            RefundTypeEnum::Full->value => 'payments.admin.payment_refund.instant_refund_widget.refund_type.full',
            RefundTypeEnum::Partial->value => 'payments.admin.payment_refund.instant_refund_widget.refund_type.partial',
        ];

        $refundType = $form->addRadioList(
            'refund_type',
            'payments.admin.payment_refund.instant_refund_widget.refund_type.label',
            $refundTypeOptions,
        )->setDefaultValue(RefundTypeEnum::Full->value);

        $refundAmount = $form->addFloat(
            'refund_amount',
            'payments.admin.payment_refund.instant_refund_widget.refund_amount.label',
        )
            ->setDefaultValue($payment->amount)
            ->setHtmlAttribute('min', '0.01')
            ->setHtmlAttribute('max', $payment->amount)
            ->setHtmlAttribute('step', '0.01')
            ->setHtmlAttribute('readonly')
            ->setHtmlAttribute('class', 'form-control disabled');

        $refundAmount
            ->addConditionOn($instantRefund, $form::Equal, true)
            ->addRule(
                $form::Filled,
                'payments.admin.payment_refund.instant_refund_widget.refund_amount.invalid',
            )
            ->addRule(
                $form::Pattern,
                'payments.admin.payment_refund.instant_refund_widget.refund_amount.invalid',
                '^\d+(\.\d{1,2})?$',
            )
            ->addConditionOn($refundType, $form::Equal, RefundTypeEnum::Partial->value)
            ->addRule(
                $form::Range,
                'payments.admin.payment_refund.instant_refund_widget.refund_amount.range',
                [0.01, $payment->amount],
            )
            ->elseCondition()
            ->addRule(
                $form::Equal,
                'payments.admin.payment_refund.instant_refund_widget.refund_amount.must_equal_full',
                $payment->amount,
            );

        return $form;
    }

    public function render(array $params): void
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
            throw new RuntimeException("Unable to refund payment, payment '{$paymentId}' doesn't exist.");
        }

        $gateway = $this->gatewayFactory->getGateway($payment->payment_gateway->code);
        if (!$gateway instanceof RefundableInterface) {
            throw new RuntimeException(
                "Unable to refund payment, gateway '{$payment->payment_gateway->code}' doesn't support refunds.",
            );
        }

        $refundAmount = $this->resolveRefundAmount($payment, $values);

        $result = $gateway->refund($payment, $refundAmount);

        if ($result === RefundStatusEnum::Failure) {
            $form->addError('payments.admin.payment_refund.instant_refund_widget.refund_error');
            return [$form, $values];
        }

        $this->refundPaymentProcessor->processRefundedPayment($payment, $refundAmount);

        return [$form, $values];
    }

    private function resolveRefundAmount(ActiveRow $payment, ArrayHash $values): float
    {
        $refundAmount = match ($values->refund_type) {
            RefundTypeEnum::Full->value => $payment->amount,
            RefundTypeEnum::Partial->value => $values->refund_amount,
            default => null,
        };

        if ($refundAmount === null) {
            throw new RuntimeException(
                "Refund type is missing or invalid for payment '{$payment->id}', refund aborted.",
            );
        }

        if ($refundAmount <= 0 || $refundAmount > $payment->amount) {
            throw new RuntimeException(
                "Calculated refund amount is out of valid range for payment '{$payment->id}', refund aborted.",
            );
        }

        return $refundAmount;
    }
}
