<?php

namespace Crm\PaymentsModule\DI;

use Contributte\Translation\DI\TranslationProviderInterface;
use Crm\PaymentsModule\Commands\RecurrentPaymentsChargeCommand;
use Crm\PaymentsModule\Models\Gateways\GatewayAbstract;
use Nette\Application\IPresenterFactory;
use Nette\DI\CompilerExtension;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

final class PaymentsModuleExtension extends CompilerExtension implements TranslationProviderInterface
{
    public function loadConfiguration()
    {
        // load services from config and register them to Nette\DI Container
        $this->compiler->loadDefinitionsFromConfig(
            $this->loadFromFile(__DIR__.'/../config/config.neon')['services'],
        );
    }

    public function getConfigSchema(): Schema
    {
        return Expect::structure([
            'gateway_test_host' => Expect::string()->dynamic(),
            'fastcharge_threshold' => Expect::int()->default(24),
        ]);
    }

    public function beforeCompile()
    {
        $builder = $this->getContainerBuilder();
        // load presenters from extension to Nette
        $builder->getDefinition($builder->getByType(IPresenterFactory::class))
            ->addSetup('setMapping', [['Payments' => 'Crm\PaymentsModule\Presenters\*Presenter']]);

        foreach ($builder->findByType(GatewayAbstract::class) as $definition) {
            $definition->addSetup('setTestHost', [$this->config->gateway_test_host]);
        }

        foreach ($builder->findByType(RecurrentPaymentsChargeCommand::class) as $definition) {
            $definition->addSetup('setFastChargeThreshold', [$this->config->fastcharge_threshold]);
        }
    }

    /**
     * Return array of directories, that contain resources for translator.
     * @return string[]
     */
    public function getTranslationResources(): array
    {
        return [__DIR__ . '/../lang/'];
    }
}
