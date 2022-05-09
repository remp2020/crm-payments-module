<?php

namespace Crm\PaymentsModule\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;

class CancelAuthorizationCommand extends Command
{

    protected function configure()
    {
        $this->setName('payments:cancel_authorization')
            ->setDescription('Cancel authorization payments');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("**** <info>Command is deprecated - authorization payments are automatically canceled by bank.</info> ****");
        Debugger::log('Command is deprecated - authorization payments are automatically canceled by bank.');

        return Command::SUCCESS;
    }
}
