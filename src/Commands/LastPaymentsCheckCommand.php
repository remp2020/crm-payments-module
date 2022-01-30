<?php

namespace Crm\PaymentsModule\Commands;

use Crm\ApplicationModule\ActiveRow;
use Crm\ApplicationModule\ActiveRowFactory;
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

    private $emitter;

    private $emailTempate = 'problems_with_payments_notification';

    /** @var InputInterface */
    private $input;

    /** @var OutputInterface */
    private $output;

    private $activeRowFactory;

    /** @var array */
    private $emails = [];

    /** @var array */
    private $overrides = [];

    /** @var string */
    private $startOfDay;

    /** @var string */
    private $endOfDay;

    /** @var bool */
    private $isNight = false;

    public function __construct(
        PaymentGatewaysRepository $paymentGatewaysRepository,
        PaymentsRepository $paymentsRepository,
        Emitter $emitter,
        ActiveRowFactory $activeRowFactory
    ) {
        parent::__construct();
        $this->paymentGatewaysRepository = $paymentGatewaysRepository;
        $this->paymentsRepository = $paymentsRepository;
        $this->emitter = $emitter;
        $this->activeRowFactory = $activeRowFactory;
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
                'day-start',
                null,
                InputOption::VALUE_OPTIONAL,
                "Select start of day (manual payments are not checked through night)",
                "today 08:00"
            )
            ->addOption(
                'day-end',
                null,
                InputOption::VALUE_OPTIONAL,
                "Select end of day (manual payments are not checked through night)",
                "today 23:00"
            )
            ->addOption(
                'exclude',
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                "Codes of gateways to exclude from checking"
            )
            ->addOption(
                'override',
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                <<<EOH
Overrides options for one gateway. Format:
`--override={GATEWAY-CODE},{COUNT-TO-CHECK},{HOURS-TO-CHECK}`.
EOH
            )
            ->setHelp(<<<EOH
This command checks all payment gateways (except excluded by <comment>`--exclude`</comment> option) for errors.

Notification will be sent if users are unable to finish payment process. Either:
  - last <info>{COUNT-TO-CHECK}</info> payments are unsuccessful (in error or form state); and / or
  - no valid payment was done in last <info>{HOURS-TO-CHECK}</info> hours.

In case you need to setup on gateway with different settings, you can use <comment>`--override`</comment>.

Format: <comment>`--override={GATEWAY-CODE},{COUNT-TO-CHECK},{HOURS-TO-CHECK}`</comment> where:
  - <info>{GATEWAY-CODE}</info>   - eg.: bank_transfer, paypal, paypal_reference, ...
  - <info>{COUNT-TO-CHECK}</info> - sends notification if last <info>{COUNT-TO-CHECK}</info> payments are not valid.
  - <info>{HOURS-TO-CHECK}</info> - sends notification if no valid payments are present in <info>{HOURS-TO-CHECK}</info> hours.
EOH
        )
            ->addUsage('--notify=email@example.com')
            ->addUsage('--notify=email@example.com --exclude=bank_transfer')
            ->addUsage('--notify=email@example.com --override=paypal,10,2')
            ->addUsage('--notify=email@example.com --day-start=7AM --day-end="22:00"')
            ->setDescription('Check last payments if there is some errors');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->output->writeln('');
        $this->output->writeln('<info>***** CHECK LAST PAYMENTS *****</info>');
        $this->output->writeln('');

        $this->emails = $input->getOption('notify');
        $exclude = $input->getOption('exclude');

        $this->processOverrideOption();
        $this->processDayOptions();

        // define night (based on day-start and day-end; everything between is night)
        $now = new DateTime();
        if ($now > $this->endOfDay || $now < $this->startOfDay) {
            $this->isNight = true;
        }

        /** @var ActiveRow $gateway */
        foreach ($this->paymentGatewaysRepository->getAllVisible() as $gateway) {
            if (in_array($gateway->code, $exclude)) {
                continue;
            }

            $this->output->writeln("Checking gateway <info>{$gateway->name}</info>");

            $checkCount = self::CHECK_LAST_PAYMENTS;
            $checkHours = self::CHECK_LAST_HOURS;
            if (isset($this->overrides[$gateway->code])) {
                $checkCount = $this->overrides[$gateway->code]['count'];
                $checkHours = $this->overrides[$gateway->code]['hours'];
            }

            // check for PAID payment in last hours -----------------------
            $error = $this->checkLastHours($gateway, $checkHours);
            if ($error !== null) {
                $this->sendNotification($gateway, $error);
            }

            // check if last payments are not all failed ----------------------
            $error = $this->checkLastPayments($gateway, $checkCount);
            if ($error !== null) {
                $this->sendNotification($gateway, $error);
            }

            $this->output->writeln("Done gateway <info>{$gateway->name}</info>\n");
        }

        return Command::SUCCESS;
    }

    /**
     * @param ActiveRow $gateway
     * @param int $hours - How many hours to check. Defaults to self::CHECK_LAST_HOURS.
     * @return string|null - Returns NULL if everything is OK; error message if there is error
     */
    private function checkLastHours(ActiveRow $gateway, int $hours = self::CHECK_LAST_HOURS): ?string
    {
        $this->output->writeln("Checking payments for last {$hours} hours");

        // do not check non-recurrent gateways at night
        if (!$gateway->is_recurrent && $this->isNight) {
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
        if ($this->isNight) {
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
     * @param int $checkCount - How many previous payments to check? Defaults to self::CHECK_LAST_PAYMENTS.
     * @return string|null - Returns NULL if everything is OK; error message if there is error
     */
    private function checkLastPayments(ActiveRow $gateway, int $checkCount = self::CHECK_LAST_PAYMENTS): ?string
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
            $userRow = $this->activeRowFactory->create([
                'email' => $email,
            ]);
            $this->emitter->emit(new NotificationEvent($this->emitter, $userRow, $this->emailTempate, [
                'gateway' => $gateway,
                'error' => $error,
            ]));
        }
    }


    /**
     * Processes override option array and sets $overrides private field.
     *
     * Single override is processed from format
     *
     *    `gateway-code,count-to-check,hours-to-check`
     *
     * to multi array:
     *
     *    $overrides[$gatewayCode] => [
     *       'count' => $countToCheck,
     *       'hours' => $hoursToCheck,
     *    ];
     *
     * Result is set to private field $overrides.
     *
     * @throws \Exception
     */
    private function processOverrideOption()
    {
        $overrides = [];

        foreach ($this->input->getOption('override') as $override) {
            $o = explode(',', $override);

            if (empty($o[0])) {
                throw new \Exception("Override's 1nd parameter cannot be empty. Provide payment gateway to override.");
            }

            $count = filter_var($o[1], FILTER_VALIDATE_INT);
            if ($count === false || $count <= 0) {
                throw new \Exception("Override's 2nd parameter (count) must be integer bigger than 0. Got {$o[1]}. See help for details.");
            }
            $hours = filter_var($o[2], FILTER_VALIDATE_INT);
            if ($hours === false || $hours <= 0) {
                throw new \Exception("Override's 3rd parameter (hours) must be integer bigger than 0. Got {$o[2]}. See help for details.");
            }

            $overrides[$o[0]] = [
                'count' => $count,
                'hours' => $hours,
            ];
        }

        $this->overrides = $overrides;
    }


    /**
     * Processes options `day-start` and `day-end` and stores them in private fields $startOfDay and $endOfDay.
     *
     * @throws \Exception If day-start is bigger than day-end (day should start before it ends, right?)
     */
    private function processDayOptions()
    {
        $dayStart = DateTime::from($this->input->getOption('day-start'));
        $dayEnd = DateTime::from($this->input->getOption('day-end'));

        if ($dayStart >= $dayEnd) {
            throw new \Exception("Day's start (got {$dayStart}) must be before day's end ({$dayEnd}).");
        }

        $this->startOfDay = $dayStart;
        $this->endOfDay = $dayEnd;
    }
}
