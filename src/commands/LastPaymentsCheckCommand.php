<?php

namespace Crm\PaymentsModule\Commands;

use Crm\ApplicationModule\ActiveRow;
use Crm\ApplicationModule\DataRow;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\UsersModule\Events\NotificationEvent;
use League\Event\Emitter;
use Nette\Utils\DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class LastPaymentsCheckCommand extends Command
{
    const CHECK_LAST_HOURS = 2;

    const CHECK_LAST_PAYMENTS = 10;

    private $paymentGatewaysRepository;

    private $paymentsRepository;

    /** @var array */
    private $emails = [];

    private $emitter;

    private $emailTempate = 'problems_with_payments_notification';

    /** @var OutputInterface */
    private $output;

    public function __construct(
        PaymentGatewaysRepository $paymentGatewaysRepository,
        PaymentsRepository $paymentsRepository,
        Emitter $emitter
    ) {
        parent::__construct();
        $this->paymentGatewaysRepository = $paymentGatewaysRepository;
        $this->paymentsRepository = $paymentsRepository;
        $this->emitter = $emitter;
    }

    protected function configure()
    {
        $this->setName('payments:last_payments_check')
            ->addOption(
                'notify',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                "E-mail addresses of people to notify about the issue."
            )
            ->addOption(
                'exclude',
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                "Codes of gateways to exclude from checking"
            )
            ->setDescription('Check last payments if there is some errors');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->output->writeln('');
        $this->output->writeln('<info>***** CHECK LAST PAYMENTS *****</info>');
        $this->output->writeln('');

        $this->emails = $input->getOption('notify');
        $exclude = $input->getOption('exclude');

        /** @var ActiveRow $gateway */
        foreach ($this->paymentGatewaysRepository->getAllVisible() as $gateway) {
            if (in_array($gateway->code, $exclude)) {
                continue;
            }

            $this->output->writeln("Checking gateway <info>{$gateway->name}</info>");

            // check for PAID payment in last two hours -----------------------
            $error = $this->checkLastHours($gateway, self::CHECK_LAST_HOURS);
            if ($error !== null) {
                $this->sendNotification($gateway, $error);
            }

            // check if last payments are not all failed ----------------------
            $error = $this->checkLastPayments($gateway, self::CHECK_LAST_PAYMENTS);
            if ($error !== null) {
                $this->sendNotification($gateway, $error);
            }

            $this->output->writeln("Done gateway <info>{$gateway->name}</info>\n");
        }
    }

    /**
     * @param ActiveRow $gateway
     * @param int $hours - How many hours to check.
     * @return string|null - Returns NULL if everything is OK; error message if there is error
     */
    private function checkLastHours(ActiveRow $gateway, int $hours): ?string
    {
        $this->output->writeln("Checking payments for last {$hours} hours");

        // define night (when less manual payments occur as time between midnight and 8am
        $isNight = false;
        $now = new DateTime();
        $morning = DateTime::from('today 8am');
        // +15 minutes allows cron to run around midnight
        $quarterPastMidnight = DateTime::from('today 00:15');
        if ($now <= $morning && $now >= $quarterPastMidnight) {
            $isNight = true;
        }

        // do not check non-recurrent gateways at night
        if (!$gateway->is_recurrent && $isNight) {
            $this->output->writeln(' * <comment>Skipping night</comment> for non recurrent gateway');
            return null;
        }

        $paidGatewayPayments = $this->paymentsRepository->all('', $gateway)
            ->where('status = ?', PaymentsRepository::STATUS_PAID)
            ->where('paid_at > ?', DateTime::from("now - {$hours} hours"));

        // all payments check (whole day)
        if ((clone $paidGatewayPayments)->count() === 0) {
            return "no PAID payment in last {$hours} hours for gateway: {$gateway->name}";
        }

        // recurrent charges check
        if ($gateway->is_recurrent && (clone $paidGatewayPayments)->where('recurrent_charge = 1')->count() === 0) {
            return "no PAID payment in last {$hours} hours for gateway: {$gateway->name} (automatic charges)";
        }

        // skip manual payments at night
        if ($isNight) {
            $this->output->writeln(' * <comment>Skipping night</comment> for manual payments');
            return null;
        }

        // manual payments check
        if ((clone $paidGatewayPayments)->where('recurrent_charge = 0')->count() === 0) {
            return "no PAID payment in last {$hours} hours for gateway: {$gateway->name} (manual payments)";
        }

        return null;
    }


    /**
     * @param ActiveRow $gateway
     * @param int $checkCount
     * @return string|null - Returns NULL if everything is OK; error message if there is error
     */
    private function checkLastPayments(ActiveRow $gateway, int $checkCount): ?string
    {
        $this->output->writeln("Checking last {$checkCount} payments");
        $lastPayments = $this->paymentsRepository->all('', $gateway)->order('created_at DESC')->limit($checkCount);
        $form = 0;
        $paid = 0;
        $error = 0;
        $timeout = 0;
        foreach ($lastPayments as $payment) {
            if ($payment->status == PaymentsRepository::STATUS_PAID) {
                $paid++;
            }
            if ($payment->status == PaymentsRepository::STATUS_FORM) {
                $form++;
            }
            if ($payment->status == PaymentsRepository::STATUS_FAIL) {
                $error++;
            }
            if ($payment->status == PaymentsRepository::STATUS_TIMEOUT) {
                $timeout++;
            }
        }

        if ($form == $checkCount) {
            return 'form';
        } elseif ($error == $checkCount) {
            return 'error';
        } elseif ($timeout == $checkCount) {
            return 'timeout';
        } elseif ($paid == 0) {
            return "none of last {$checkCount} payments has status PAID";
        }

        return null;
    }


    /**
     * @param ActiveRow $gateway
     * @param string $error
     */
    private function sendNotification(ActiveRow $gateway, string $error)
    {
        $this->output->writeln(" * Sending <error>notification</error> <info>{$error}</info>");
        foreach ($this->emails as $email) {
            $userRow = new DataRow([
                'email' => $email,
            ]);
            $this->emitter->emit(new NotificationEvent($userRow, $this->emailTempate, [
                'gateway' => $gateway,
                'error' => $error,
            ]));
        }
    }
}
