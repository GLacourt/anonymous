<?php

declare(strict_types=1);

namespace Anonymous\DependencyInjection\Compiler;

use Anonymous\AnonymizerRegistryInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\Bundle\DoctrineBundle\Command\Proxy\ImportDoctrineCommand;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Exception;
use LogicException;
use Symfony\Bridge\Doctrine\Middleware\Debug\Middleware as SfDebugMiddleware;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class AnonymizerPass
 */
class AnonymizerPass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     * @throws Exception
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
    }
}
