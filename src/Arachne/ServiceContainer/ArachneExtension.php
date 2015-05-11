<?php

/*
 * This file is part of the Arachne package.
 *
 * (c) Wojtek Gancarczyk <gancarczyk@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Arachne\ServiceContainer;

use Arachne\Validation;
use Behat\Behat\Context\ServiceContainer\ContextExtension;
use Behat\Testwork\ServiceContainer\Extension;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class ArachneExtension
 * @package Arachne\ServiceContainer
 * @author Wojtek Gancarczyk <gancarczyk@gmail.com>
 */
class ArachneExtension implements Extension
{
    const VALIDATION_PROVIDER_REF = 'arachne.ref.validation_provider';
    const CLIENT_REF = 'arachne.ref.client';
    const AUTH_PROVIDER_REF = 'arachne.ref.auth_provider';
    const FILE_LOCATOR_REF = 'arachne.ref.file_locator';

    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getConfigKey()
    {
        return 'arachne';
    }

    /**
     * {@inheritDoc}
     */
    public function initialize(ExtensionManager $extensionManager)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function configure(ArrayNodeDefinition $builder)
    {
        $builder
            ->children()
                ->scalarNode('base_url')->isRequired()->end()
                ->arrayNode('paths')
                    ->children()
                        ->scalarNode('schema_file_dir')
                            ->defaultValue('%paths.base%/schemas')
                        ->end()
                        ->scalarNode('request_file_dir')
                            ->defaultValue('%paths.base%/requests')
                        ->end()
                        ->scalarNode('response_file_dir')
                            ->defaultValue('%paths.base%/responses')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('auth')
                    ->children()
                        ->variableNode('provider')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('http_client')
                    ->children()
                        ->variableNode('headers')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end();
    }

    /**
     * {@inheritDoc}
     */
    public function load(ContainerBuilder $container, array $config)
    {
        $this->loadFileLocator($container, $config['paths']);
        $this->loadValidationProvider($container);
        $this->loadClient($container, $config);
        $this->loadAuthProvider($container, $config);
        $this->loadContextInitializer($container);
    }

    /**
     * @param ContainerBuilder $container
     * @param array $config
     * @return void
     */
    private function loadFileLocator(ContainerBuilder $container, array $config)
    {
        $definition = new Definition('Arachne\FileSystem\FileLocator', array($config));
        $container->setDefinition(self::FILE_LOCATOR_REF, $definition);
    }

    /**
     * @param ContainerBuilder $container
     * @return void
     */
    private function loadValidationProvider(ContainerBuilder $container)
    {
        // TODO load before and provide configurable (json, xml) schema validation
        $definition = new Definition(
            'Arachne\Validation\Provider',
            array(
                new Reference(self::FILE_LOCATOR_REF),
                new Validation\Schema\JsonSchema,
                'json'
            )
        );
        $container->setDefinition(self::VALIDATION_PROVIDER_REF, $definition);
    }

    /**
     * @param ContainerBuilder $container
     * @param array $config
     * @return void
     */
    private function loadClient(ContainerBuilder $container, array $config)
    {
        $definition = new Definition(
            'Arachne\Http\Client\Guzzle',
            array(
                $config['base_url'],
                new Reference(self::FILE_LOCATOR_REF)
            )
        );
        $container->setDefinition(self::CLIENT_REF, $definition);
    }

    /**
     * @param ContainerBuilder $container
     * @param array $config
     * @return void
     */
    private function loadAuthProvider(ContainerBuilder $container, array $config)
    {
        if (!isset($config['auth']['provider'])) {
            return;
        }

        $definition = new Definition(
            $config['auth']['provider'],
            array(
                new Reference(self::CLIENT_REF),
            )
        );
        $container->setDefinition(self::AUTH_PROVIDER_REF, $definition);
    }

    /**
     * @param ContainerBuilder $container
     * @return void
     */
    private function loadContextInitializer(ContainerBuilder $container)
    {
        $definition = new Definition(
            'Arachne\Context\Initializer\ArachneInitializer',
            array(
                new Reference(self::VALIDATION_PROVIDER_REF),
                new Reference(self::CLIENT_REF),
                new Reference(self::AUTH_PROVIDER_REF),
            )
        );
        $definition->addTag(ContextExtension::INITIALIZER_TAG, array('priority' => 0));
        $container->setDefinition('arachne.context_initializer', $definition);
    }
}
