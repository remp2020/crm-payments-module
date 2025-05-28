<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

interface FetchCardInformationCommandProviderInterface
{
    /**
     * @param InputInterface $input contains options listed in Crm\PaymentsModule\Commands\FetchCardInformationCommand
     * @param OutputInterface $output
     * @return void
     */
    public function fetch(InputInterface $input, OutputInterface $output): void;
}
