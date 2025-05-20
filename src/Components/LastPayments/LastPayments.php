<?php

namespace Crm\PaymentsModule\Components\LastPayments;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\PaymentsModule\Repositories\PaymentsRepository;

/**
 * This widgest fetches last payments for specific for payment gateway
 * and renders bootstrap listing.
 *
 * @package Crm\PaymentsModule\Components
 */
class LastPayments extends BaseLazyWidget
{
    private $view = 'last_payments';

    private $where = [];

    private $limit;

    /** @var PaymentsRepository */
    private $paymentsRepository;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        PaymentsRepository $paymentsRepository,
    ) {
        parent::__construct($lazyWidgetManager);
        $this->paymentsRepository = $paymentsRepository;
    }

    public function setUserId($userId)
    {
        $this->where['user_id'] = $userId;
        return $this;
    }

    public function setPaymentGatewayId($paymentGatewayId)
    {
        $this->where['payment_gateway_id'] = $paymentGatewayId;
        return $this;
    }

    public function setSubscriptionTypeId($subscriptionTypeId)
    {
        $this->where['subscription_type_id'] = $subscriptionTypeId;
        return $this;
    }

    public function setSalesFunnelId($salesFunnelId)
    {
        $this->where['sales_funnel_id'] = $salesFunnelId;
        return $this;
    }

    public function setLimit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    public function render()
    {
        $payments = $this->paymentsRepository->all()->order('created_at DESC');
        if (empty($this->where)) {
            throw new \Exception('You cannot list all payments withou where');
        }
        $payments->where($this->where);
        if ($this->limit) {
            $payments->limit($this->limit);
        }

        $this->template->payments = $payments;
        $this->template->setFile(__DIR__ . '/' . $this->view . '.latte')->render();
    }
}
