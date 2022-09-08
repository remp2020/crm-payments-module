<?php

namespace Crm\PaymentsModule\DI;

use Contributte\Translation\DI\TranslationProviderInterface;
use Nette\DI\CompilerExtension;
use Nette\Schema\Expect;

final class PaymentsModuleExtension extends CompilerExtension implements TranslationProviderInterface
{
    public function loadConfiguration()
    {
        $builder = $this->getContainerBuilder();
        // load services from config and register them to Nette\DI Container
        $this->compiler->loadDefinitionsFromConfig(
            $this->loadFromFile(__DIR__.'/../config/config.neon')['services']
        );

        foreach ($builder->findByType(\Crm\PaymentsModule\Gateways\GatewayAbstract::class) as $definition) {
            $definition->addSetup('setTestHost', [$this->config->gateway_test_host]);
        }
    }

    public function getConfigSchema(): \Nette\Schema\Schema
    {
        return Expect::structure([
            'gateway_test_host' => Expect::string()->dynamic(),
        ]);
    }

    public function beforeCompile()
    {
        $builder = $this->getContainerBuilder();
        // load presenters from extension to Nette
        $builder->getDefinition($builder->getByType(\Nette\Application\IPresenterFactory::class))
            ->addSetup('setMapping', [['Payments' => 'Crm\PaymentsModule\Presenters\*Presenter']]);
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
