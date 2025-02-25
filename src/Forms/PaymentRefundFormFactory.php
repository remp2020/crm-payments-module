<?php

namespace Crm\PaymentsModule\Forms;

use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\UI\Form;
use Crm\PaymentsModule\DataProviders\PaymentRefundFormDataProviderInterface;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\SubscriptionsModule\Events\SubscriptionShortenedEvent;
use Crm\SubscriptionsModule\Models\Subscription\StopSubscriptionHandler;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use DateTime;
use League\Event\Emitter;
use Nette\Database\Table\ActiveRow;
use Nette\Localization\Translator;
use Nette\Utils\ArrayHash;
use Tomaj\Form\Renderer\BootstrapRenderer;

class PaymentRefundFormFactory
{
    const PAYMENT_ID_KEY = 'payment_id';
    const SUBSCRIPTION_ENDS_AT_KEY = 'subscription_ends_at';
    const NEW_PAYMENT_STATUS = 'new_payment_status';
    const STOP_RECURRENT_CHARGE_KEY = 'stop_recurrent_charge';

    /** @var callable */
    public $onSave;

    public function __construct(
        private DataProviderManager $dataProviderManager,
        private PaymentsRepository $paymentsRepository,
        private StopSubscriptionHandler $stopSubscriptionHandler,
        private SubscriptionsRepository $subscriptionsRepository,
        private RecurrentPaymentsRepository $recurrentPaymentsRepository,
        private Translator $translator,
        private Emitter $emitter,
    ) {
    }

    /**
     * @throws DataProviderException
     */
    public function create(int $paymentId): Form
    {
        $form = new Form();
        $form->addProtection();
        $form->setTranslator($this->translator);
        $form->setRenderer(new BootstrapRenderer());

        $payment = $this->paymentsRepository->find($paymentId);

        $form->addHidden(self::PAYMENT_ID_KEY)->setRequired()->setDefaultValue($payment->id);

        // For case, if you want to change final payment status via dataProvider
        $form->addHidden(self::NEW_PAYMENT_STATUS)
            ->setRequired()
            ->setDefaultValue(PaymentsRepository::STATUS_REFUND);

        $now = new DateTime();
        if ($payment->subscription && $payment->subscription->end_time > $now) {
            if ($payment->status != PaymentsRepository::STATUS_REFUND) {
                $form->addText(
                    self::SUBSCRIPTION_ENDS_AT_KEY,
                    'payments.admin.payment_refund.form.cancel_subscription_date'
                )
                    ->setRequired('payments.admin.payment_refund.form.required.subscription_ends_at')
                    ->setHtmlAttribute('class', 'flatpickr')
                    ->setHtmlAttribute('flatpickr_datetime', "1")
                    ->setHtmlAttribute('flatpickr_datetime_seconds', "1")
                    ->setHtmlAttribute('flatpickr_mindate', $now->format('d.m.Y H:i:s'))
                    ->setDefaultValue($now->format('Y-m-d H:i:s'));
            }

            $form->addHidden('subscription_default_ends_at')
                ->setRequired()
                ->setHtmlId('subscription_default_ends_at')
                ->setDefaultValue($payment->subscription->end_time);

            $form->addHidden('subscription_starts_at')
                ->setRequired()
                ->setHtmlId('subscription_starts_at')
                ->setDefaultValue($payment->subscription->start_time);
        }

        if ($this->recurrentPaymentCanBeStoppedInRefund($payment) && $payment->status != PaymentsRepository::STATUS_REFUND) {
            $form->addCheckbox(
                self::STOP_RECURRENT_CHARGE_KEY,
                'payments.admin.payment_refund.form.stop_recurrent_charge'
            )
                ->setDisabled()
                ->setDefaultValue(true);
        }

        if ($payment->status != PaymentsRepository::STATUS_REFUND) {
            $form->addSubmit('submit', 'payments.admin.payment_refund.confirm_refund')
                ->getControlPrototype()
                ->setName('button')
                ->setAttribute('class', 'btn btn-danger');
        }

        /** @var PaymentRefundFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            PaymentRefundFormDataProviderInterface::PATH,
            PaymentRefundFormDataProviderInterface::class
        );
        foreach ($providers as $provider) {
            $form = $provider->provide(['form' => $form]);
        }

        $form->onSuccess[] = [$this, 'formSucceeded'];

        return $form;
    }

    public function formSucceeded(Form $form, ArrayHash $values): void
    {
        $payment = $this->paymentsRepository->find($values[self::PAYMENT_ID_KEY]);
        $newEndTime = $values[self::SUBSCRIPTION_ENDS_AT_KEY] ?? null;
        $newPaymentStatus = $values[self::NEW_PAYMENT_STATUS] ?? $payment->status;
        $stopRecurrentPayment = $values[self::STOP_RECURRENT_CHARGE_KEY] ?? true;

        if ($newEndTime && $payment->subscription_id) {
            $this->updateSubscriptionOnSuccess($payment->subscription, new DateTime($newEndTime));
        }

        if ($stopRecurrentPayment && $this->recurrentPaymentCanBeStoppedInRefund($payment)) {
            $this->stopRecurrentChargeInRefundedPayment($payment);
        }

        $this->paymentsRepository->updateStatus($payment, $newPaymentStatus, true);

        /** @var PaymentRefundFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            PaymentRefundFormDataProviderInterface::PATH,
            PaymentRefundFormDataProviderInterface::class
        );
        foreach ($providers as $provider) {
            [$form, $values] = $provider->formSucceeded($form, $values);
        }

        if (isset($this->onSave)) {
            ($this->onSave)($values[self::PAYMENT_ID_KEY]);
        }
    }

    protected function updateSubscriptionOnSuccess(ActiveRow $subscription, DateTime $newEndTime): void
    {
        if ($newEndTime <= new DateTime()) {
            $this->stopSubscriptionHandler->stopSubscription($subscription, true);
        } else {
            $this->shortenSubscription($subscription, $newEndTime);
        }
    }

    protected function recurrentPaymentCanBeStoppedInRefund(ActiveRow $payment): bool
    {
        $recurrentPayment = $this->recurrentPaymentsRepository->recurrent($payment);

        if (!$recurrentPayment) {
            return false;
        }

        $lastRecurrentPayment = $this->recurrentPaymentsRepository->getLastWithState(
            $recurrentPayment,
            RecurrentPaymentsRepository::STATE_ACTIVE,
        );

        return $lastRecurrentPayment
            && $this->recurrentPaymentsRepository->canBeStopped($lastRecurrentPayment);
    }

    protected function stopRecurrentChargeInRefundedPayment(ActiveRow $payment): void
    {
        $recurrentPayment = $this->recurrentPaymentsRepository->recurrent($payment);
        $lastRecurrentPayment = $this->recurrentPaymentsRepository->getLastWithState(
            $recurrentPayment,
            RecurrentPaymentsRepository::STATE_ACTIVE,
        );

        $this->recurrentPaymentsRepository->stoppedByAdmin($lastRecurrentPayment);
    }

    protected function shortenSubscription(ActiveRow $subscription, DateTime $newEndTime): void
    {
        $note = '[Admin shortened] From ' . $subscription->end_time->format('Y-m-d H:i:s') . ' to ' . $newEndTime->format('Y-m-d H:i:s');
        if (!empty($subscription->note)) {
            $note = $subscription->note . "\n" . $note;
        }

        $this->subscriptionsRepository->update($subscription, [
            'end_time' => $newEndTime,
            'note' => $note,
        ]);

        $this->emitter->emit(new SubscriptionShortenedEvent($subscription, $newEndTime));
    }
}
