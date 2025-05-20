<?php

namespace Crm\PaymentsModule\Populator;

use Crm\ApplicationModule\Populators\AbstractPopulator;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\RecurrentPayment\RecurrentPaymentStateEnum;
use Symfony\Component\Console\Helper\ProgressBar;

class PaymentsPopulator extends AbstractPopulator
{
    /**
     * @param ProgressBar $progressBar
     */
    public function seed($progressBar)
    {
        $payments = $this->database->table('payments');
        $recurrentPayments = $this->database->table('recurrent_payments');
        $paymentMethods = $this->database->table('payment_methods');

        for ($i = 0; $i < $this->count; $i++) {
            $user = $this->getRecord('users');
            $data = [
                'variable_symbol' => $this->faker->numerify('##########'),
                'user_id' => $user->id,
                'created_at' => $this->faker->dateTimeBetween('-1 years'),
                'modified_at' => $this->faker->dateTimeBetween('-1 years'),
                'ip' => $this->faker->ipv4,
                'user_agent' => $this->faker->userAgent,
                'referer' => $this->faker->url,
            ];

            $subscription = false;
            if (random_int(1, 4) == 2) {
                $subscription = $this->getRecord('subscriptions');
            }
            $paymentGateway = $this->getRecord('payment_gateways');

            if ($subscription) {
                $data['status'] = 'paid';
                $data['amount'] = $subscription->subscription_type->price;
                $data['payment_gateway_id'] = $paymentGateway->id;
                $data['subscription_type_id'] = $subscription->subscription_type_id;
                $data['subscription_id'] = $subscription;
                $data['paid_at'] = $this->faker->dateTimeBetween('-1 years');
            } else {
                $subscriptionType = $this->getRecord('subscription_types');
                $data['status'] = $this->getErrorPaymentStatus();
                $data['amount'] = $subscriptionType->price;
                $data['payment_gateway_id'] = $paymentGateway->id;
                $data['subscription_type_id'] = $subscriptionType->id;
            }

            $payment = $payments->insert($data);
            if ($payment->payment_gateway->is_recurrent) {
                $creditCardNumber = $this->faker->creditCardNumber();

                $paymentMethod = $paymentMethods->insert([
                    'user_id' => $user->id,
                    'payment_gateway_id' => 2,
                    'external_token' => $creditCardNumber,
                    'created_at' => $this->faker->dateTimeBetween('-1 years'),
                    'updated_at' => $this->faker->dateTimeBetween('-1 years'),
                ]);

                $recurrentPaymentData = [
                    'cid' => $creditCardNumber,
                    'payment_method_id' => $paymentMethod->id,
                    'created_at' => $this->faker->dateTimeBetween('-1 years'),
                    'updated_at' => $this->faker->dateTimeBetween('-1 years'),
                    'payment_gateway_id' => 2,
                    'charge_at' => $this->faker->dateTimeBetween('+1 years', '+2 years'),
                    'expires_at' => $this->faker->dateTimeBetween('-1 years'),
                    'parent_payment_id' => $payment->id,
                    'retries' => $this->faker->randomNumber(3),
                    'user_id' => $user->id,
                    'subscription_type_id' => $data['subscription_type_id'],
                    'state' => RecurrentPaymentStateEnum::Active->value,
                ];
                $recurrentPayments->insert($recurrentPaymentData);
            }

            $progressBar->advance();
        }
    }

    private function getErrorPaymentStatus()
    {
        $statuses = [
            PaymentStatusEnum::Form->value,
            PaymentStatusEnum::Fail->value,
            PaymentStatusEnum::Timeout->value,
        ];
        return $statuses[random_int(0, count($statuses)-1)];
    }
}
