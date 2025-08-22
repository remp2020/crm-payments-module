<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Crm\ApplicationModule\UI\Form;
use Nette\Database\Table\ActiveRow;

interface PaymentFormGeneralSettingsDataProviderInterface extends DataProviderInterface
{
    /**
     * Provide can add/change or remove form field/component of form.
     *
     * It can define `onValidate()` / `onSubmit()` callbacks (defined within provider) which Form will call
     * when validation / submit occur.
     *
     * @param array $params {
     *   @type Form $form
     *   @type ActiveRow $payment
     * }
     */
    public function provide(array $params): Form;

    /**
     * This method is called when form is successfully submitted.
     *
     * @param Form $form
     * @param mixed $values
     */
    public function formSucceeded(Form $form, $values, ActiveRow $payment): void;
}
