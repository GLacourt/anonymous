<?php

declare(strict_types=1);

namespace Anonymous\DependencyInjection;

use Anonymous\Anonymizer\AnonymizerInterface;
use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * Class AnonymousExtension
 */
class AnonymousExtension extends Extension implements PrependExtensionInterface
{

    /**
     * @param ContainerBuilder $container
     */
    public function prepend(ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(
            __DIR__.'/../../bundles/DoctrineBundle/Resources/config'
        ));

        $loader->load('dbal.xml');
    }

    /**
     * @param array            $configs
     * @param ContainerBuilder $container
     *
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.xml');

        $loader = new XmlFileLoader($container, new FileLocator(
            __DIR__.'/../../bundles/DoctrineBundle/Resources/config'
        ));
        $loader->load('dbal.xml');

        dump('Good loader 2');

        $config = $this->processConfiguration(new Configuration(), $configs);

        if ($container->hasDefinition('Anonymous\Anonymizer')) {
            $definition = $container->getDefinition('Anonymous\Anonymizer')
                ->replaceArgument('$config', $config['mapping']);
            ;

            $container->setDefinition('Anonymous\Anonymizer', $definition);
        }

        $container
            ->registerForAutoconfiguration(AnonymizerInterface::class)
            ->addTag('anonymous.anonymizer');
    }
}
