<?php

namespace Crm\PaymentsModule\Commands;

use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RepairRecurrentUpgradesCommand extends Command
{
    private $recurrentPaymentsRepository;

    private $paymentsRepository;

    private $subscriptionsRepository;

    private $subscriptionTypesRepository;

    private $paymentGatewaysRepository;

    private $usersRepository;

    public function __construct(
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        PaymentsRepository $paymentsRepository,
        SubscriptionsRepository $subscriptionsRepository,
        SubscriptionTypesRepository $subscriptionTypesRepository,
        PaymentGatewaysRepository $paymentGatewaysRepository,
        UsersRepository $usersRepository
    ) {
        parent::__construct();
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->paymentsRepository = $paymentsRepository;
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
        $this->paymentGatewaysRepository = $paymentGatewaysRepository;
        $this->usersRepository = $usersRepository;
    }

    protected function configure()
    {
        $this->setName('payments:repair_recurrent_upgrades')
            ->setDescription('Repair recurrent upgrades charging incorrect amounts');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('');
        $output->writeln('<info>***** Repairing upgrades *****</info>');
        $output->writeln('');

        $sql = <<<SQL
SELECT r1.user_id, email, r1.cid, r1.payment_gateway_id, r1.subscription_type_id, 
cast(r1.custom_amount AS DECIMAL(10,2)) as "correct_amount", cast(p2.amount AS DECIMAL(10,2)) AS "bad_amount", r2.id AS "to_upgrade" FROM (

SELECT user_id, cid, custom_amount, charge_at, subscription_type_id, payment_id, payment_gateway_id FROM recurrent_payments
WHERE note IS NOT NULL
AND note NOT LIKE '%AutoStop%'
AND custom_amount IS NOT NULL

) r1
JOIN users ON r1.user_id = users.id
LEFT JOIN recurrent_payments r2 ON r1.user_id = r2.user_id AND r1.cid = r2.cid AND r2.custom_amount is null AND r2.charge_at > r1.charge_at AND r1.subscription_type_id = r2.subscription_type_id
LEFT JOIN payments p1 ON p1.id = r1.payment_id
LEFT JOIN payments p2 ON p2.id = r2.payment_id
WHERE r2.id IS NOT NULL
ORDER BY r2.charge_at ASC;
SQL;

        $incorrectlyChargedUsers = [];
        $db = $this->recurrentPaymentsRepository->getDatabase();

        $db->beginTransaction();

        try {
            $query = $db->query($sql);

            /** @var ActiveRow $row */
            foreach ($query->fetchAll() as $row) {
                if ($row->bad_amount) { // people already charged incorrect amount
                    if ($row->correct_amount == 2.89) {
                        $rp = $this->recurrentPaymentsRepository->find($row->to_upgrade);
                        $this->recurrentPaymentsRepository->update($rp, [
                            'state' => RecurrentPaymentsRepository::STATE_ADMIN_STOP,
                        ]);
                        $output->writeln("User [{$row->email}] was fixed automatically by stopping recurrent payment due to invalid upgrade");
                    } else {
                        $rp = $this->recurrentPaymentsRepository->find($row->to_upgrade);
                        $this->recurrentPaymentsRepository->update($rp, [
                            'custom_amount' => $row->correct_amount,
                        ]);
                        $output->writeln("User [{$row->email}] was fixed automatically by setting amount [{$row->correct_amount}] for recurrent payment [{$rp->id}]");
                    }

                    if (!in_array($row->email, $incorrectlyChargedUsers)) {
                        $subscriptionType = $this->subscriptionTypesRepository->find($row->subscription_type_id);
                        $gateway = $this->paymentGatewaysRepository->find($row->payment_gateway_id);
                        $user = $this->usersRepository->find($row->user_id);

                        $actualSubscription = $this->subscriptionsRepository->actualUserSubscription($user->id);

                        if (is_array($actualSubscription)) {
                            $startTime = null;
                            foreach ($actualSubscription as $as) {
                                if ($startTime == null || $as->end_time > $startTime) {
                                    $startTime = $as->end_time;
                                }
                            }
                        } elseif ($actualSubscription == null) {
                            $startTime = new DateTime();
                        } else {
                            /** @var DateTime $startTime */
                            $startTime = $actualSubscription->end_time;
                        }

                        $endTime = (clone $startTime)->modify('+7 days');
                        $output->writeln("User [{$row->email}] also received free 7 day subscription from [{$startTime->format('Y-m-d H:i:s')}] to [{$endTime->format('Y-m-d H:i:s')}]");

                        $this->subscriptionsRepository->add(
                            $subscriptionType,
                            $gateway ? $gateway->is_recurrent : false,
                            $user,
                            'free',
                            $startTime,
                            $endTime,
                            'odskodne za vyssiu strhnutu sumu'
                        );
                        $incorrectlyChargedUsers[] = $row->email;
                    }

                    continue;
                }

                $rp = $this->recurrentPaymentsRepository->find($row->to_upgrade);
                $this->recurrentPaymentsRepository->update($rp, [
                    'custom_amount' => $row->correct_amount,
                ]);
                $output->writeln("User [{$row->email}] was fixed automatically by setting amount [{$row->correct_amount}] for recurrent payment [{$rp->id}]");

                if ($row->correct_amount == 2.89) {
                    $rp = $this->recurrentPaymentsRepository->find($row->to_upgrade);
                    $this->recurrentPaymentsRepository->update($rp, [
                        'state' => RecurrentPaymentsRepository::STATE_ADMIN_STOP,
                    ]);
                    $output->writeln("User [{$row->email}] was fixed automatically by stopping recurrent payment due to invalid upgrade [2.89]");
                }
            }
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        $db->commit();
    }
}
