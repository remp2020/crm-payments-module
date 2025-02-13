<?php

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Crm\ApplicationModule\UI\Form;
use Nette\Database\Table\Selection;

interface AdminFilterFormDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params): Form;

    /**
     * @param Selection $selection
     * @param array $formData Form values parsed by AdminFilterFormData.
     * @return Selection
     */
    public function filter(Selection $selection, array $formData): Selection;
}
