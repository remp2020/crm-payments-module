<?php

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;

interface PaymentItemTypesFilterDataProviderInterface extends DataProviderInterface
{
    /**
     * @param array $params Associative array consists of two other arrays with keys: 'paymentItemTypes' and 'paymentItemTypesDefaultFilter'
     *                      paymentItemTypes - Associative array with key => value pairs.
     *                          key - identifier of payment item type filter
     *                          value - string displayed as select option
     *                      paymentItemTypesDefaultFilter - Use keys from paymentItemTypes to set selected default filter.
     * @return mixed
     */
    public function provide(array $params);

    /**
     * @param array $selectedTypes Selected payment item types keys to use in filter
     * @return mixed NULL if there is no filter or query string which is combined with other dataproviders into WHERE clause connected by OR.
     */
    public function filter($selectedTypes);
}
