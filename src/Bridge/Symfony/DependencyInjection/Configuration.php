<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Symfony\DependencyInjection;

use JsonApi\Symfony\Http\Negotiation\MediaType;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('jsonapi');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        /** @var NodeBuilder $children */
        $children = $rootNode->children();
        $children->booleanNode('strict_content_negotiation')
            ->defaultTrue()
        ;

        /** @var NodeBuilder $children */
        $children = $rootNode->children();
        $children->scalarNode('media_type')
            ->defaultValue(MediaType::JSON_API)
        ;

        $children->scalarNode('route_prefix')
            ->defaultValue('/api')
        ;

        $children->arrayNode('pagination')
            ->addDefaultsIfNotSet()
            ->children()
                ->integerNode('default_size')->min(1)->defaultValue(25)->end()
                ->integerNode('max_size')->min(1)->defaultValue(100)->end()
            ->end()
        ;

        $children->arrayNode('sorting')
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('whitelist')
                    ->useAttributeAsKey('type')
                    ->arrayPrototype()
                        ->scalarPrototype()->end()
                    ->end()
                ->end()
            ->end()
        ;

        $children->arrayNode('write')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('allow_relationship_writes')->defaultFalse()->end()
                ->arrayNode('client_generated_ids')
                    ->useAttributeAsKey('type')
                    ->booleanPrototype()->end()
                    ->defaultValue([])
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
