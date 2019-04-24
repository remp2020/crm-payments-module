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

    private $gatewayFactory;

    public function __construct(
        Context $database,
        GatewayFactory $gatewayFactory,
        IStorage $cacheStorage = null
    ) {
        parent::__construct($database, $cacheStorage);
        $this->gatewayFactory = $gatewayFactory;
    }

    public function all()
    {
        return $this->getTable()
            ->where('code IN (?)', $this->gatewayFactory->getRegisteredCodes())
            ->order('sorting');
    }

    public function find($id)
    {
        return $this->getTable()
            ->where(['id' => $id])
            ->where('code IN (?)', $this->gatewayFactory->getRegisteredCodes())
            ->fetch();
    }

    public function findByCode($code)
    {
        return $this->getTable()
            ->where(['code' => $code])
            ->where('code IN (?)', $this->gatewayFactory->getRegisteredCodes())
            ->limit(1)
            ->fetch();
    }

    /**
     * @return Selection
     */
    public function getAllVisible()
    {
        return $this->all()->where(['visible' => true]);
    }

    public function getAllVisibleWithTag($tag)
    {
        return $this->getAllVisible()
            ->where('code IN (?)', $this->gatewayFactory->getTaggedCodes($tag));
    }

    public function add(
        $name,
        $code,
        $sorting = 10,
        $visible = true,
        $isRecurrent = false
    ) {
        return $this->insert([
            'name' => $name,
            'code' => $code,
            'sorting' => $sorting,
            'visible' => $visible,
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
