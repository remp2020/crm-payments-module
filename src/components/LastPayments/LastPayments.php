<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\PaymentsModule\Repository\PaymentsRepository;

/**
 * Bootstrap listing of last payments for specific for payment gateway.
 * Used in payment gateway detail.
 *
 * @package Crm\PaymentsModule\Components
 */
class LastPayments extends BaseWidget
{
    private $view = 'last_payments';

    private $where = [];

    private $limit;

    /** @var  PaymentsRepository */
    private $paymentsRepository;

    public function __construct(WidgetManager $widgetManager, PaymentsRepository $paymentsRepository)
    {
        parent::__construct($widgetManager);
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
