<?php

namespace SfRestExtension;

use Behat\Testwork\ServiceContainer\ExtensionManager;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Behat\Behat\Context\ServiceContainer\ContextExtension;
use Behat\Testwork\ServiceContainer\Extension;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Behat extension to store references
 */
class SfRestExtension implements Extension
{
    /**
     * Extension configuration ID.
     */
    const MOD_ID = 'sf_db_connection';

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigKey()
    {
        return self::MOD_ID;
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(ExtensionManager $extensionManager)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function configure(ArrayNodeDefinition $builder)
    {
        $builder->children()
            ->scalarNode('user')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode("pw")->isRequired()->cannotBeEmpty()->end()
            ->scalarNode("token")->isRequired()->cannotBeEmpty()->end()
            ->scalarNode("wdsl_path")->isRequired()->cannotBeEmpty()->end()
            ->scalarNode("endpoint")->defaultValue("https://test.salesforce.com/services/Soap/c/32.0")->cannotBeEmpty()->end()
            ;
    }

    /**
     * {@inheritdoc}
     */
    public function load(ContainerBuilder $container, array $config)
    {
        $definition = new Definition('SfRestExtension\Initializer\SFDBInitializer', [
            $config['user'], $config['pw'], $config['token'], $config['wdsl_path'], 
            $config['endpoint']
        ]);
        $definition->addTag(ContextExtension::INITIALIZER_TAG, ['priority' => 0]);
        $container->setDefinition('sf_db_connection.context_initializer', $definition);
    }
}
