<?php

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Crm\ApplicationModule\UI\Form;

interface RetentionAnalysisDataProviderInterface extends DataProviderInterface
{
    /**
     * Interface function provides a chance to add additional filtering parameters to retention analysis configuration.
     * Array $params has 'form' key, containing \Nette\Forms\Form object and 'inputParams' key, containing array of user-entered form parameters (e.g. to pre-fill default parameters).
     * Optionally, it also contains `disable` key, representing boolean value saying if any added form input should be disabled.
     *
     * @param array $params
     *
     * @return Form
     */
    public function provide(array $params): Form;

    /**
     * The function should reflect user-entered values for custom inputs added in provide() function and add required conditions to retention analysis filtering.
     * All conditions has to be expressed using WHERE (+parameters) and JOIN SQL conditions which are combined with all other applied filters.
     *
     * @param array $wheres array to add required SQL WHERE conditions (all conditions will be connected with AND operator). Keyword WHERE is added automatically, parameter placeholders may be used.
     *                     Example: $wheres[] = 'sales_funnel_id IN (?)';
     * @param array $whereParams All placeholders added to $wheres array should have associated parameters added to $whereParams array
     *                           Example: $whereParams[] = $salesFunnelId;
     * @param array $joins array to add required SQL JOINs to retention analysis filtering
     *                     Example: $joins[] = 'LEFT JOIN subscriptions s1 ON payments.user_id = s1.user_id AND s1.created_at < payments.paid_at';
     * @param array $inputParams contains array of user-entered form parameters
     */
    public function filter(array &$wheres, array &$whereParams, array &$joins, array $inputParams): void;
}
