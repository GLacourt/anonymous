<?php
declare(strict_types=1);

namespace Anonymous\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('anonymous');
        $rootNode    = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->booleanNode('pagination')
                    ->info('Enable or disable the pagination')
                    ->defaultFalse()
                ->end()
                ->integerNode('page_size')
                    ->info('Set the size of the pagination')
                    ->defaultValue(100)
                    ->min(1)
                ->end()
                ->arrayNode('mapping')
                    ->info('Set the mapping with entities, properties and anonymizer to be used.')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('entity')
                    ->defaultValue([])
                    ->prototype('variable')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
