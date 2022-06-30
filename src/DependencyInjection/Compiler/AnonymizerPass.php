<?php

declare(strict_types=1);

namespace Anonymous\DependencyInjection\Compiler;

use Anonymous\AnonymizerRegistryInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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
    }
}
