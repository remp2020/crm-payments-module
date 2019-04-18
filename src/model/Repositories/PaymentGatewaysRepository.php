<?php

namespace Crm\PaymentsModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\PaymentsModule\GatewayFactory;
use DateTime;
use Nette\Caching\IStorage;
use Nette\Database\Context;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;

class PaymentGatewaysRepository extends Repository
{
    protected $tableName = 'payment_gateways';

    private $registeredGateways = [];

    public function __construct(
        Context $database,
        GatewayFactory $gatewayFactory,
        IStorage $cacheStorage = null)
    {
        parent::__construct($database, $cacheStorage);
        $this->registeredGateways = $gatewayFactory->getRegisteredCodes();
    }

    public function all()
    {
        return $this->getTable()
            ->where('code IN (?)', $this->registeredGateways)
            ->order('sorting');
    }

    public function find($id)
    {
        return $this->getTable()
            ->where(['id' => $id])
            ->where('code IN (?)', $this->registeredGateways)
            ->fetch();
    }

    public function findByCode($code)
    {
        return $this->getTable()
            ->where(['code' => $code])
            ->where('code IN (?)', $this->registeredGateways)
            ->limit(1)
            ->fetch();
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
        return $this->all()->where('shop', true);
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
