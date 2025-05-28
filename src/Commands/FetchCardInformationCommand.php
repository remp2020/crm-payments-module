<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Commands;

use Crm\ApplicationModule\Commands\DecoratedCommandTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FetchCardInformationCommand extends Command
{
    use DecoratedCommandTrait;

    private array $providers = [];

    public function configure(): void
    {
        $this->setName('payments:fetch_card_information')
            ->setDescription('Fetches card information for every registered FetchCardInformationProvider.')
            ->addOption(
                'from',
                'f',
                InputOption::VALUE_REQUIRED,
            )
            ->addOption(
                'user_id',
                'u',
                InputOption::VALUE_REQUIRED,
            );
    }

    public function registerProvider(FetchCardInformationCommandProviderInterface $cardInformationProvider): void
    {
        $this->providers[] = $cardInformationProvider;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if (empty($this->providers)) {
            $this->warn('No fetch card information providers registered.');
        }

        /** @var FetchCardInformationCommandProviderInterface $provider */
        foreach ($this->providers as $provider) {
            $provider->fetch($input, $output);
        }

        $this->info('Done.');
        return Command::SUCCESS;
    }
}
