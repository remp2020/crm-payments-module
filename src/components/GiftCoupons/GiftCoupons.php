<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Database\IRow;

class GiftCoupons extends BaseWidget
{
    private $templateName = 'gift_coupons.latte';

    private $usersRepository;

    public function __construct(
        WidgetManager $widgetManager,
        UsersRepository $usersRepository
    ) {
        parent::__construct($widgetManager);

        $this->usersRepository = $usersRepository;
    }

    public function header($id = '')
    {
        return 'coupon modal';
    }

    public function identifier()
    {
        return 'couponmodal';
    }

    public function render(IRow $payment)
    {
        $giftCoupons = $payment->related('payment_gift_coupons')->fetchAll();
        $users = [];

        if (empty($giftCoupons)) {
            return;
        }

        foreach ($giftCoupons as $giftCoupon) {
            $users[$giftCoupon->email] = $this->usersRepository->getByEmail($giftCoupon->email);
        }

        $this->template->users = $users;
        $this->template->payment = $payment;
        $this->template->giftCoupons = $giftCoupons;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
