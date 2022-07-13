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
     *
     * @return void
     */
    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('doctrine')) {
            $container->prependExtensionConfig('doctrine', $this->getConfig());
        }
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

        $config = $this->processConfiguration(new Configuration(), $configs);

        if ($container->hasDefinition('Anonymous\Anonymizer')) {
            $definition = $container->getDefinition('Anonymous\Anonymizer')
                ->replaceArgument('$config', $config['mapping']);

            $container->setDefinition('Anonymous\Anonymizer', $definition);
        }

        $container
            ->registerForAutoconfiguration(AnonymizerInterface::class)
            ->addTag('anonymous.anonymizer');
    }

    /**
     * @return array
     */
    private function getConfig(): array
    {
        return [
            'dbal' => [
                'connections' => [
                    'anonymous' => [
                        'url' => '%env(anonymous:DATABASE_URL)%',
                    ],
                ],
            ],
            'orm'  => [
                'entity_managers' => [
                    'anonymous' => [
                        'connection'      => 'anonymous',
                        'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                        'mappings'        => [
                            'Anonymous' => [
                                'is_bundle' => false,
                                'dir'       => '%kernel.project_dir%/src/Entity',
                                'prefix'    => 'App\Entity',
                                'alias'     => 'Anonymous',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
