<?php

namespace Crm\PaymentsModule\Models\SuccessPageResolver;

use Nette\Database\Table\ActiveRow;

interface PaymentCompleteRedirectResolver
{
    /**
     * PAID represents valid paid payment, that ended up successfully.
     */
    const PAID = 'paid';

    /**
     * NOT_SETTLED represents state where payment will most likely be confirmed in the near future,
     * but the gateway provider didn't make the final confirmation yet.
     */
    const NOT_SETTLED = 'not_settled';

    /**
     * CANCELLED is used when user intentionally cancels the payment process on the payment gateway provider site.
     */
    const CANCELLED = 'cancelled';

    /**
     * ERROR is used if there's any kind of unexpected error during payment processing.
     */
    const ERROR = 'error';

    /**
     * FORM is used for payments, that didn't really go through external gateway and will be confirmed offline later
     * (e.g. manually, by importing bank statements or by reading notification emails from gateway provider)
     */
    const FORM = 'form';

    /**
     * shouldRedirect decides whether the implementation should be used to redirect user after successful payment to
     * custom location based on arbitrary condition using $payment instance.
     *
     * @param ActiveRow $payment instance of completed payment
     * @param string $status completion status of payment; use one of the constants provided by PaymentCompleteRedirectResolver
     * @return bool
     */
    public function wantsToRedirect(?ActiveRow $payment, string $status): bool;

    /**
     * redirectArgs return array of arguments to be used in presenter's ->redirect() method.
     *
     * Expected usage is to trust the result and use it directly as parameters (example in presenter's context):
     *   $this->redirect(...$resolver->redirectArgs($payment));
     *
     * @param ActiveRow $payment instance of completed payment
     * * @param string $status completion status of payment; use one of the constants provided by PaymentCompleteRedirectResolver
     * @return array
     */
    public function redirectArgs(?ActiveRow $payment, string $status): array;
}
