<?php

namespace Crm\PaymentsModule\Repository;

use Crm\ApplicationModule\Repository;
use DateTime;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;

class PaymentGatewaysRepository extends Repository
{
    const TYPE_COMFORT_PAY = 'comfortpay';
    const TYPE_PAYPAL = 'paypal';
    const TYPE_PAYPAL_RECURRENT = 'paypalrecurrent';

    protected $tableName = 'payment_gateways';

    public function getAllActive()
    {
        return $this->getTable()->where(['active' => true])->order('sorting');
    }

    public function findByCode($code)
    {
        return $this->getTable()->where(['code' => $code])->limit(1)->fetch();
    }

    public function allActiveRecurrent()
    {
        return $this->getAllActive()->where('is_recurrent', true);
    }

    public function allRecurrent()
    {
        return $this->getTable()->where(['is_recurrent' => true]);
    }

    /**
     * @return Selection
     */
    public function getAllVisible()
    {
        return $this->getTable()->where(['active' => true, 'visible' => true])->order('sorting');
    }

    public function getAllActiveShop()
    {
        return $this->getAllActive()->where('shop', true);
    }


    public function all()
    {
        return $this->getTable()->order('sorting');
    }

    public function add(
        $name,
        $code,
        $sorting = 10,
        $active = true,
        $visible = true,
        $shop = false,
        $description = null,
        $image = null,
        $default = false,
        $isRecurrent = false
    ) {
        return $this->insert([
            'name' => $name,
            'code' => $code,
            'sorting' => $sorting,
            'active' => $active,
            'visible' => $visible,
            'shop' => $shop,
            'description' => $description,
            'default' => $default,
            'image' => $image,
            'is_recurrent' => $isRecurrent,
            'created_at' => new DateTime(),
            'modified_at' => new DateTime(),
        ]);
    }

    public function exists($code)
    {
        return $this->getTable()->where(['code' => $code])->count('*') > 0;
    }

    public function update(IRow &$row, $data)
    {
        $values['modified_at'] = new DateTime();
        parent::update($row, $data);
    }
}
