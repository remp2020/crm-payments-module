<?php

namespace Crm\PaymentsModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\PaymentsModule\GatewayFactory;
use DateTime;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class PaymentGatewaysRepository extends Repository
{
    protected $tableName = 'payment_gateways';

    private $gatewayFactory;

    public function __construct(
        Explorer $database,
        GatewayFactory $gatewayFactory,
        Storage $cacheStorage = null
    ) {
        parent::__construct($database, $cacheStorage);
        $this->gatewayFactory = $gatewayFactory;
    }

    final public function all()
    {
        return $this->getTable()
            ->where('code IN (?)', $this->gatewayFactory->getRegisteredCodes())
            ->order('sorting');
    }

    final public function find($id)
    {
        return $this->getTable()
            ->where(['id' => $id])
            ->where('code IN (?)', $this->gatewayFactory->getRegisteredCodes())
            ->fetch();
    }

    final public function findByCode($code)
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
    final public function getAllVisible()
    {
        return $this->all()->where(['visible' => true]);
    }

    final public function add(
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

    final public function exists($code)
    {
        return $this->getTable()->where(['code' => $code])->count('*') > 0;
    }

    final public function update(ActiveRow &$row, $data)
    {
        $data['modified_at'] = new DateTime();
        return parent::update($row, $data);
    }
}
