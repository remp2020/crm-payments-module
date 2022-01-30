<?php

namespace Crm\PaymentsModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderException;
use Crm\ApplicationModule\Graphs\Criteria;
use Crm\ApplicationModule\Graphs\GraphDataItem;
use Crm\SubscriptionsModule\DataProvider\EndingSubscriptionsDataProviderInterface;
use Nette\Localization\Translator;

class SubscriptionsWithoutExtensionEndingWithinPeriodDataProvider implements EndingSubscriptionsDataProviderInterface
{
    private $translator;

    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    public function provide(array $params): GraphDataItem
    {
        if (!isset($params['dateFrom'])) {
            throw new DataProviderException('dateFrom param missing');
        }
        if (!isset($params['dateTo'])) {
            throw new DataProviderException('dateTo param missing');
        }

        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('subscriptions')
            ->setJoin('LEFT JOIN payments ON payments.subscription_id=subscriptions.id
                        LEFT JOIN recurrent_payments ON payments.id = recurrent_payments.parent_payment_id')
            ->setWhere('  AND next_subscription_id IS NULL AND (recurrent_payments.id IS NULL OR recurrent_payments.state != \'active\' or recurrent_payments.status is not null or recurrent_payments.retries = 0)')
            ->setTimeField('end_time')
            ->setValueField('count(*)')
            ->setStart($params['dateFrom'])
            ->setEnd($params['dateTo']));
        $graphDataItem->setName($this->translator->translate('dashboard.subscriptions.ending.nonext.title'));
        return $graphDataItem;
    }
}
