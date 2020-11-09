<?php

namespace Crm\PaymentsModule\Repository;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Crm\PaymentsModule\Events\RecurrentPaymentRenewedEvent;
use Crm\PaymentsModule\Events\RecurrentPaymentStateChangedEvent;
use Crm\PaymentsModule\Events\RecurrentPaymentStoppedByAdminEvent;
use Crm\PaymentsModule\Events\RecurrentPaymentStoppedByUserEvent;
use Exception;
use League\Event\Emitter;
use Nette\Database\Context;
use Nette\Database\Table\IRow;
use Nette\Utils\DateTime;
use Tracy\Debugger;

class RecurrentPaymentsRepository extends Repository
{
    protected $tableName = 'recurrent_payments';

    protected $auditLogExcluded = [
        'updated_at',
    ];

    const STATE_USER_STOP = 'user_stop';
    const STATE_ADMIN_STOP = 'admin_stop';
    const STATE_ACTIVE = 'active';
    const STATE_PENDING = 'pending';
    const STATE_CHARGED = 'charged';
    const STATE_CHARGE_FAILED = 'charge_failed';
    const STATE_SYSTEM_STOP = 'system_stop';

    private $emitter;

    private $applicationConfig;

    private $hermesEmitter;

    public function __construct(
        Context $database,
        AuditLogRepository $auditLogRepository,
        Emitter $emitter,
        ApplicationConfig $applicationConfig,
        \Tomaj\Hermes\Emitter $hermesEmitter
    ) {
        parent::__construct($database);
        $this->auditLogRepository = $auditLogRepository;
        $this->emitter = $emitter;
        $this->applicationConfig = $applicationConfig;
        $this->hermesEmitter = $hermesEmitter;
    }

    final public function add($cid, $payment, $chargeAt, $customAmount, $retries)
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
            'state' => self::STATE_ACTIVE,
        ]);
    }

    final public function createFromPayment(IRow $payment, string $recurrentToken): ?IRow
    {
        if ($payment->status !== PaymentsRepository::STATUS_PAID) {
            Debugger::log("Could not create recurrent payment from payment [{$payment->id}], invalid payment status: [{$payment->status}]");
            return null;
        }

        // check if recurrent payment already exists and return existing instance
        $recurrentPayment = $this->recurrent($payment);
        if ($recurrentPayment !== false) {
            return $recurrentPayment;
        }

        $retries = explode(', ', $this->applicationConfig->get('recurrent_payment_charges'));
        $retries = count((array)$retries);

        return $this->add(
            $recurrentToken,
            $payment,
            $this->calculateChargeAt($payment),
            null,
            --$retries
        );
    }

    final public function update(IRow &$row, $data)
    {
        $fireEvent = false;
        if (isset($data['state']) && $data['state'] !== $row->state) {
            $fireEvent = true;
        }

        $data['updated_at'] = new DateTime();
        $result = parent::update($row, $data);

        if ($fireEvent) {
            $this->emitter->emit(new RecurrentPaymentStateChangedEvent($row));
            $this->hermesEmitter->emit(new HermesMessage('recurrent-payment-state-changed', [
                'recurrent_payment_id' => $row->id,
            ]));
        }

        return $result;
    }

    final public function setCharged(IRow $recurrentPayment, $payment, $status, $approval)
    {
        $fireEvent = true;
        if ($recurrentPayment->state === self::STATE_CHARGED) {
            $fireEvent = false;
        }

        $this->update($recurrentPayment, [
            'payment_id' => $payment->id,
            'state' => self::STATE_CHARGED,
            'status' => $status,
            'approval' => $approval,
        ]);

        if ($fireEvent) {
            $this->emitter->emit(new RecurrentPaymentRenewedEvent($recurrentPayment));
            $this->hermesEmitter->emit(new HermesMessage('recurrent-payment-renewed', [
                'recurrent_payment_id' => $recurrentPayment->id,
            ]));
        }
    }

    final public function getChargeablePayments()
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
    final public function getUserActiveRecurrentPayments($userId)
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
    final public function userRecurrentPayments($userId)
    {
        return $this->getTable()
            ->where(['user_id' => $userId]);
    }

    final public function reactiveByUser($id, $userId)
    {
        $rp = $this->getTable()->where(['user_id' => $userId, 'id' => $id])->fetch();
        if ($rp == null) {
            return null;
        }
        $this->update($rp, ['state' => self::STATE_ACTIVE]);
        return $rp;
    }

    final public function stoppedByUser($id, $userId)
    {
        $rp = $this->getTable()->where(['user_id' => $userId, 'id' => $id])->fetch();
        if ($rp == null) {
            return null;
        }
        if (!($this->canBeStopped($rp))) {
            throw new Exception('Recurrent payment ID ' . $rp->id . ' cannot be stopped by user');
        }

        $this->update($rp, ['state' => self::STATE_USER_STOP]);
        $this->emitter->emit(new RecurrentPaymentStoppedByUserEvent($rp));
        return $rp;
    }

    final public function stoppedByGDPR($userId)
    {
        $rps = $this->getTable()->where([
            'user_id' => $userId,
            'state' => 'active'])->fetchAll();

        foreach ($rps as $rp) {
            $this->update($rp, ['state' => self::STATE_USER_STOP]);
            $this->emitter->emit(new RecurrentPaymentStoppedByUserEvent($rp));
        }

        return true;
    }

    final public function stoppedByAdmin($id)
    {
        $rp = $this->find($id);
        if ($rp == null) {
            return null;
        }
        if (!($this->canBeStopped($rp))) {
            throw new Exception('Recurrent payment ID ' . $rp->id . ' cannot be stopped by admin');
        }

        $this->update($rp, ['state' => self::STATE_ADMIN_STOP]);
        $this->emitter->emit(new RecurrentPaymentStoppedByAdminEvent($rp));
        return $rp;
    }

    final public function stoppedBySystem($id)
    {
        $rp = $this->find($id);
        if ($rp == null) {
            return null;
        }
        $this->update($rp, ['state' => self::STATE_SYSTEM_STOP]);
        return $rp;
    }

    final public function getChargableBefore($date)
    {
        return $this->getTable()
            ->where('charge_at < ?', $date);
    }

    final public function all($problem = null, $subscriptionType = null, $status = null)
    {
        $where = [];
        if ($subscriptionType) {
            $where['subscription_type_id'] = $subscriptionType;
        }
        if ($status) {
            $where['status'] = $status;
        }
        if ($problem) {
            $where['state'] = [self::STATE_SYSTEM_STOP, self::STATE_CHARGE_FAILED];
        }
        return $this->getTable()->where($where)->order('recurrent_payments.charge_at DESC, recurrent_payments.created_at DESC');
    }

    final public function getStatusPairs()
    {
        return $this->getTable()->select('status')->group('status')->fetchPairs('status', 'status');
    }

    final public function isStoppedBySubscription(IRow $subscription)
    {
        $payment = $this->database->table('payments')->where(['subscription_id' => $subscription->id])->limit(1)->fetch();
        if ($payment) {
            $recurrent = $this->recurrent($payment);
            return $this->isStopped($recurrent);
        }
        return false;
    }

    final public function isStopped($recurrent)
    {
        if (!$recurrent) {
            return true;
        }
        if (in_array($recurrent->state, [self::STATE_SYSTEM_STOP, self::STATE_USER_STOP, self::STATE_ADMIN_STOP])) {
            return true;
        }
        if ($recurrent->state == self::STATE_CHARGE_FAILED) {
            // najdeme najnovsi rekurent s tymto cid a zistime ci je stopnuty
            $newRecurrent = $this->getTable()->where(['cid' => $recurrent->cid, 'charge_at > ' => $recurrent->charge_at])->order('charge_at DESC')->limit(1)->fetch();
            if ($newRecurrent && in_array($newRecurrent->state, [self::STATE_SYSTEM_STOP, self::STATE_USER_STOP, self::STATE_ADMIN_STOP])) {
                return true;
            }
        }
        return false;
    }

    final public function hasUserStopped($userId)
    {
        return $this->getTable()->where(['user_id' => $userId, 'state' => self::STATE_USER_STOP])->count('*');
    }

    final public function recurrent(IRow $payment)
    {
        return $this->getTable()->where(['parent_payment_id' => $payment->id])->fetch();
    }

    final public function findByPayment(IRow $payment)
    {
        return $this->findBy('payment_id', $payment->id);
    }

    final public function getLastWithState(IRow $recurrentPayment, $state)
    {
        return $this->getTable()->where([
            'cid' => $recurrentPayment->cid,
            'payment_gateway_id' => $recurrentPayment->payment_gateway_id,
            'user_id' => $recurrentPayment->user_id,
            'state' => $state,
        ])->order('charge_at DESC')->fetch();
    }

    final public function getDuplicate()
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

    final public function calculateChargeAt($payment)
    {
        $subscriptionType = $payment->subscription_type;
        $subscription = $payment->subscription;

        $endTime = clone $subscription->end_time;

        $chargeBefore = null;
        if (!$chargeBefore) {
            $chargeBefore = $subscriptionType->recurrent_charge_before;
        }
        if (!$chargeBefore) {
            $configSetting = $this->applicationConfig->get('recurrent_charge_before');
            if ($configSetting !== null && $configSetting !== '') {
                $validatedSetting = filter_var($configSetting, FILTER_VALIDATE_INT);
                if ($validatedSetting !== false && $validatedSetting >= 0) {
                    $chargeBefore = $validatedSetting;
                } else {
                    Debugger::log("Global setting [recurrent_charge_before] ignored due to invalid value: " . $configSetting, Debugger::WARNING);
                }
            }
        }
        if ($chargeBefore) {
            $newEndTime = (clone $endTime)->sub(new \DateInterval("PT{$chargeBefore}H"));
            if ($newEndTime < $subscription->start_time) {
                Debugger::log("Calculated next charge of recurrent payment would be sooner than subscription start time. Check subscription: " . $subscription->id, Debugger::WARNING);
            }
            $endTime = $newEndTime;
        }

        return $endTime;
    }

    final public function getStates()
    {
        return [
            self::STATE_USER_STOP,
            self::STATE_ADMIN_STOP,
            self::STATE_ACTIVE,
            self::STATE_PENDING,
            self::STATE_CHARGED,
            self::STATE_CHARGE_FAILED,
            self::STATE_SYSTEM_STOP,
        ];
    }

    final public function canBeStopped($recurrentPayment): bool
    {
        return $recurrentPayment->parent_payment->status !== PaymentsRepository::STATUS_PREPAID;
    }
}
