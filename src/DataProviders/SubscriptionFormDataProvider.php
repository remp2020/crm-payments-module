<?php

namespace Crm\PaymentsModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderException;
use Crm\ApplicationModule\Helpers\UserDateHelper;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SubscriptionsModule\DataProviders\SubscriptionFormDataProviderInterface;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\TextInput;
use Nette\Localization\Translator;
use Nette\Utils\DateTime;
use Nette\Utils\Html;
use Tracy\Debugger;

class SubscriptionFormDataProvider implements SubscriptionFormDataProviderInterface
{
    private $paymentsRepository;

    private $subscriptionsRepository;

    private $translator;

    private $userDateHelper;

    public function __construct(
        Translator $translator,
        PaymentsRepository $paymentsRepository,
        SubscriptionsRepository $subscriptionsRepository,
        UserDateHelper $userDateHelper
    ) {
        $this->paymentsRepository = $paymentsRepository;
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->translator = $translator;
        $this->userDateHelper = $userDateHelper;
    }

    public function provide(array $params): Form
    {
        if (!isset($params['form'])) {
            throw new DataProviderException('form param missing');
        }
        if (!($params['form'] instanceof Form)) {
            throw new DataProviderException('form is not instance of \Nette\Application\UI\Form');
        }

        $form = $params['form'];

        // if no subscription is attached to form, new subscription without payment is being created; nothing to do
        $subscriptionId = $form->getComponent('subscription_id', false);
        if ($subscriptionId === null) {
            return $form;
        }

        $subscription = $this->subscriptionsRepository->find((int) $subscriptionId->getValue());
        if (!$subscription) {
            Debugger::log("Subscription with ID [{$subscriptionId}] provided by SubscriptionForm doesn't exist.", Debugger::WARNING);
            return $form;
        }

        // subscription can be payment-less
        $payment = $this->paymentsRepository->subscriptionPayment($subscription);
        if (!$payment) {
            return $form;
        }

        // attach description and rule for "start time after payment paid" to element
        $elementName = 'start_time';
        if ($form->getComponent($elementName) !== null) {
            $description = $this->translator->translate(
                'payments.form.subscription_form.start_time.after_payment.description',
                ['payment_paid' => $this->userDateHelper->process($payment->paid_at)]
            );

            // load & translate original description
            // - if translation string is single value of description, it is translated automatically
            // - if there are multiple strings, translation has to be done manually
            $originalDescription = $form->getComponent($elementName)->getOption('description', null);
            if ($originalDescription !== null) {
                $description = $this->translator->translate($originalDescription) . "\n" . $description;
            }

            // attach description to element
            $form->getComponent($elementName)
                ->setOption(
                    'description',
                    Html::el('span', ['class' => 'help-block'])->setHtml($description)
                );

            $form->getComponent($elementName)
                ->addRule(
                    function (TextInput $field, DateTime $paymentPaidAt) {
                        $subscriptionStartAt = DateTime::from($field->getValue());
                        // if subscription start is set after payment's paid at; everything is alright
                        return $subscriptionStartAt >= $paymentPaidAt;
                    },
                    'payments.form.subscription_form.start_time.after_payment.error',
                    $payment->paid_at
                );
        }

        return $form;
    }
}
