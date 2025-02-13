<?php

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Crm\ApplicationModule\UI\Form;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemInterface;
use Nette\Database\Table\ActiveRow;

interface PaymentFormDataProviderInterface extends DataProviderInterface
{
    /**
     * Provide can add/change or remove form field/component of form.
     *
     * It can define `onValidate()` / `onSubmit()` callbacks (defined within provider) which Form will call
     * when validation / submit occur.
     *
     * All values of \Nette\Utils\ArrayHash type are removed by `PaymentFormFactory->formSucceeded()` (search
     * for `instanceof ArrayHash`). This can be used by provider to add field/component which shouldn't be part
     * of main insert / update (and should be processed only by provider itself). Example:
     *
     * Within your DataProvider, add container with your component:
     *
     *   ```php
     *   $container = $form->addContainer(self::CONTAINER_NAME);
     *   $container->addComponent($component, self::COMPONENT_NAME);
     *   ```
     *
     * This component can be later accessed in `onValidate()` / `onSubmit()` callbacks:
     *
     *   ```php
     *   $componentValue = $values[YourDataProvider::CONTAINER_NAME][YourDataProvider::COMPONENT_NAME];
     *   ```
     *
     * But `PaymentFormFactory->formSucceeded()` won't submit this field for update.
     *
     * @param array $params {
     *   @type Form $form
     *   @type ActiveRow $payment
     * }
     */
    public function provide(array $params): Form;

    /**
     * @param array $params
     * @return PaymentItemInterface[]
     */
    public function paymentItems(array $params): array;
}
