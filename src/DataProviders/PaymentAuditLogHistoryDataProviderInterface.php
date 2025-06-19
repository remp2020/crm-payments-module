<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Nette\Database\Table\ActiveRow;

interface PaymentAuditLogHistoryDataProviderInterface extends DataProviderInterface
{
    public function provide(ActiveRow $payment): array;
}
