<?php

namespace Crm\PaymentsModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Application\Request;
use Nette\Application\UI\Form;
use Nette\Database\Table\Selection;

interface AdminFilterFormDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params): Form;

    public function filter(Selection $selection, Request $request): Selection;
}
