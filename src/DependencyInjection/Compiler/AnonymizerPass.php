<?php

declare(strict_types=1);

namespace Anonymous\DependencyInjection\Compiler;

use Anonymous\AnonymizerRegistryInterface;
use Doctrine\Bundle\DoctrineBundle\Dbal\ManagerRegistryAwareConnectionProvider;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class AnonymizerPass
 */
class AnonymizerPass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(AnonymizerRegistryInterface::class)) {
            return;
        }

        $definition  = $container->findDefinition(AnonymizerRegistryInterface::class);
        $anonymizers = $container->findTaggedServiceIds('anonymous.anonymizer');

        foreach ($anonymizers as $id => $tags) {
            $definition->addMethodCall('add', [new Reference($id)]);
        }

        $connections = $container->getParameter('doctrine.connections');
        $connections['anonymous'] = 'doctrine.dbal.anonymous_connection';

        $container->setParameter('doctrine.connections', $connections);

        $this->loadAnonymousConnection($container);
    }

    /**
     * @param ContainerBuilder $container
     *
     * @return void
     */
    protected function loadAnonymousConnection(ContainerBuilder $container): void
    {
        $configuration = $container->setDefinition('doctrine.dbal.anonymous_connection.configuration', new ChildDefinition('doctrine.dbal.connection.configuration'));
        $connectionId  = 'doctrine.dbal.anonymous_connection';

        $default = $container->getDefinition('doctrine.dbal.default_connection');

        $default = [
            'driver'   => 'pdo_pgsql',
            'host'     => 'localhost',
            'user'     => 'guillaume',
            'password' => 'root',
            'port'     => null,
            'dbname'   => 'ouibikeapi_anonymous',
        ];

        $container->setDefinition('doctrine.dbal.anonymous_connection.event_manager', new ChildDefinition('doctrine.dbal.connection.event_manager'));

        $def = $container
            ->setDefinition($connectionId, new ChildDefinition('doctrine.dbal.connection'))
            ->setPublic(true)
            ->setArguments([
                $default,
                new Reference('doctrine.dbal.anonymous_connection.configuration'),
                new Reference('doctrine.dbal.anonymous_connection.event_manager'),
                [],
            ]);

        $container
            ->registerAliasForArgument($connectionId, Connection::class, 'anonymousConnection')
            ->setPublic(false);

        $container->setDefinition(
            ManagerRegistryAwareConnectionProvider::class,
            new Definition(ManagerRegistryAwareConnectionProvider::class, [$container->getDefinition('doctrine')])
        );
    }
}
