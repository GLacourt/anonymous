<?php

declare(strict_types=1);

namespace Anonymous\DependencyInjection\Compiler;

use App\Component\Action\ActionRegistryInterface;
use App\Component\List\ListRegistryInterface;
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
//        if (!$container->has(ListRegistryInterface::class)) {
//            return;
//        }
//
//        $definition = $container->findDefinition(ListRegistryInterface::class);
//        $lists      = $container->findTaggedServiceIds('app.list');
//
//        foreach ($lists as $id => $tags) {
//            $definition->addMethodCall('add', [new Reference($id)]);
//        }
//
//        $definition = $container->findDefinition(ActionRegistryInterface::class);
//        $actions    = $container->findTaggedServiceIds('app.action');
//
//        foreach ($actions as $id => $tags) {
//            $definition->addMethodCall('add', [new Reference($id)]);
//        }
    }
}
