<?php

declare(strict_types=1);

namespace Anonymous\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * Class AnonymousExtension
 */
class AnonymousExtension extends Extension
{
    /**
     * @param array            $configs
     * @param ContainerBuilder $container
     *
     * @return void
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.xml');

        $config = $this->processConfiguration(new Configuration(), $configs);

        if ($container->hasDefinition('anonymous.anonymizer')) {
            $container->getDefinition('anonymous.anonymizer')
                ->replaceArgument(0, $config['mapping']);
            ;
        }
    }
}
