<?php

namespace Crm\PaymentsModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Application\UI\Form;
use Nette\Database\Table\Selection;

interface AdminFilterFormDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params): Form;

    /**
     * @param array $formData Form values parsed by AdminFilterFormData.
     */
    public function filter(Selection $selection, array $formData): Selection;
}
