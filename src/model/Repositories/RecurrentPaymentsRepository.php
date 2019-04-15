<?php

namespace Crm\PaymentsModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Crm\PaymentsModule\Components\ComfortPayStatus;
use Nette\Database\Context;
use Nette\Database\Table\IRow;
use Nette\Utils\DateTime;

class RecurrentPaymentsRepository extends Repository
{
    protected $tableName = 'recurrent_payments';

    const STATE_USER_STOP = 'user_stop';
    const STATE_ADMIN_STOP = 'admin_stop';
    const STATE_ACTIVE = 'active';
    const STATE_CHARGED = 'charged';
    const STATE_CHARGE_FAILED = 'charge_failed';
    const STATE_SYSTEM_STOP = 'system_stop';
    const STATE_TB_FAILED = 'tb_failed';

    public function __construct(Context $database, AuditLogRepository $auditLogRepository)
    {
        parent::__construct($database);
        $this->auditLogRepository = $auditLogRepository;
    }

    public function add($cid, $payment, $chargeAt, $customAmount, $retries)
    {
        return $this->insert([
            'cid' => $cid,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
            'charge_at' => $chargeAt,
            'payment_gateway_id' => $payment->payment_gateway->id,
            'subscription_type_id' => $payment->subscription_type_id,
            'custom_amount' => $customAmount,
            'retries' => $retries,
            'user_id' => $payment->user->id,
            'parent_payment_id' => $payment->id,
            'state' => 'active'
        ]);
    }

    public function update(IRow &$row, $data)
    {
        $data['updated_at'] = new DateTime();
        return parent::update($row, $data);
    }

    public function getChargeablePayments()
    {
        return $this->getTable()
            ->where('status IS NULL')
            ->where('retries >= 0')
            ->where('state = "active"')
            ->where(['charge_at <= ?' => DateTime::from(strtotime('+15 minutes'))])
            ->order('RAND()');
    }

    /**
     * @param $userId
     * @return \Crm\ApplicationModule\Selection
     */
    public function getUserActiveRecurrentPayments($userId)
    {
        return $this->getTable()
            ->where([
                'state' => RecurrentPaymentsRepository::STATE_ACTIVE,
                'user_id' => $userId,
            ])
            ->where('status IS NULL')
            ->where('retries >= 0')
            ->order('charge_at DESC');
    }

    /**
     * @param $userId
     * @return \Crm\ApplicationModule\Selection
     */
    public function userRecurrentPayments($userId)
    {
        return $this->getTable()->where(['user_id' => $userId])->order('id DESC, charge_at DESC');
    }

    public function reactiveByUser($id, $userId)
    {
        $rp = $this->getTable()->where(['user_id' => $userId, 'id' => $id])->fetch();
        if ($rp == null) {
            return null;
        }
        $this->update($rp, ['state' => self::STATE_ACTIVE]);
        return $rp;
    }

    public function stoppedByUser($id, $userId)
    {
        $rp = $this->getTable()->where(['user_id' => $userId, 'id' => $id])->fetch();
        if ($rp == null) {
            return null;
        }
        $this->update($rp, ['state' => self::STATE_USER_STOP]);
        return $rp;
    }

    public function stoppedByGDPR($userId)
    {
        $rps = $this->getTable()->where([
            'user_id' => $userId,
            'state' => 'active'])->fetchAll();

        foreach ($rps as $rp) {
            $this->update($rp, ['state' => self::STATE_USER_STOP]);
        }

        return true;
    }

    public function stoppedByAdmin($id)
    {
        $rp = $this->find($id);
        if ($rp == null) {
            return null;
        }
        $this->update($rp, ['state' => self::STATE_ADMIN_STOP]);
        return $rp;
    }

    public function getChargableBefore($date)
    {
        return $this->getTable()
            ->where('charge_at < ?', $date);
    }

    public function all($problem = null, $subscriptionType = null, $status = null)
    {
        $where = [];
        if ($subscriptionType) {
            $where['subscription_type_id'] = $subscriptionType;
        }
        if ($status && $problem == null) {
            $where['status'] = $status;
        }
        if ($problem) {
            $where['state = "' . self::STATE_SYSTEM_STOP . '" OR state = "' . self::STATE_TB_FAILED . '" OR state = ?'] = self::STATE_CHARGE_FAILED;
        }
        return $this->getTable()->where($where)->order('charge_at DESC, created_at DESC');
    }

    public function getStatusPairs()
    {
        $status = [];
        $rows = $this->getTable()->select('status')->group('status')->fetchAll();
        foreach ($rows as $row) {
            $status[$row->status] = ComfortPayStatus::getStatusHtml($row->status)['text'];
        }
        return $status;
    }

    public function isStopped(IRow $subscription)
    {
        $payment = $this->database->table('payments')->where(['subscription_id' => $subscription->id])->limit(1)->fetch();
        if ($payment) {
            $recurrent = $this->recurrent($payment);
            return $this->recurrentStopped($recurrent);
        }
        return false;
    }

    private function recurrentStopped($recurrent)
    {
        if (!$recurrent) {
            return true;
        }
        if (in_array($recurrent->state, [self::STATE_TB_FAILED, self::STATE_SYSTEM_STOP, self::STATE_USER_STOP, self::STATE_ADMIN_STOP])) {
            return true;
        }
        if ($recurrent->state == self::STATE_CHARGE_FAILED) {
            // najdeme najnovsi rekurent s tymto cid a zistime ci je stopnuty
            $newRecurrent = $this->getTable()->where(['cid' => $recurrent->cid, 'charge_at > ' => $recurrent->charge_at])->order('charge_at DESC')->limit(1)->fetch();
            if ($newRecurrent && in_array($newRecurrent->state, [self::STATE_TB_FAILED, self::STATE_SYSTEM_STOP, self::STATE_USER_STOP, self::STATE_ADMIN_STOP])) {
                return true;
            }
        }
        return false;
    }

    public function hasUserStopped($userId)
    {
        return $this->getTable()->where(['user_id' => $userId, 'state' => self::STATE_USER_STOP])->count('*');
    }

    public function recurrent(IRow $payment)
    {
        return $this->getTable()->where(['parent_payment_id' => $payment->id])->fetch();
    }

    public function getLastWithState(IRow $recurrentPayment, $state)
    {
        return $this->getTable()->where([
            'cid' => $recurrentPayment->cid,
            'payment_gateway_id' => $recurrentPayment->payment_gateway_id,
            'user_id' => $recurrentPayment->user_id,
            'state' => $state,
        ])->order('charge_at DESC')->fetch();
    }

    public function getDuplicate()
    {
        return $this->getTable()
            ->select('COUNT(*) AS payments')
            ->select('user_id')
            ->where('charge_at > ?', new \DateTime())
            ->where('state = ?', 'active')
            ->group('user_id')
            ->having('payments > 1')
            ->fetchAll();
    }

    public function calculateChargeAt($payment)
    {
        $subscriptionType = $payment->subscription_type;
        $subscription = $payment->subscription;

        $endTime = $subscription->end_time;
        if ($subscriptionType->recurrent_charge_before) {
            $endTime->sub(new \DateInterval("PT{$subscriptionType->recurrent_charge_before}H"));
        }

        return $endTime;
    }
}
