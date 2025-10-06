<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Symfony\DependencyInjection;

use JsonApi\Symfony\Http\Negotiation\MediaType;
use LogicException;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\IntegerNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('jsonapi');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $children = $rootNode->children();

        $children->booleanNode('strict_content_negotiation')->defaultTrue()->end();

        /** @var ScalarNodeDefinition $mediaType */
        $mediaType = $children->scalarNode('media_type');
        $mediaType->defaultValue(MediaType::JSON_API)->end();

        /** @var ScalarNodeDefinition $routePrefix */
        $routePrefix = $children->scalarNode('route_prefix');
        $routePrefix->defaultValue('/api')->end();

        $pagination = $children->arrayNode('pagination')->addDefaultsIfNotSet();
        $paginationChildren = $pagination->children();

        /** @var IntegerNodeDefinition $defaultSize */
        $defaultSize = $paginationChildren->integerNode('default_size');
        $defaultSize->min(1)->defaultValue(25)->end();

        /** @var IntegerNodeDefinition $maxSize */
        $maxSize = $paginationChildren->integerNode('max_size');
        $maxSize->min(1)->defaultValue(100)->end();

        $pagination->end();

        $sorting = $children->arrayNode('sorting')->addDefaultsIfNotSet();
        $sortingChildren = $sorting->children();

        $whitelist = $sortingChildren->arrayNode('whitelist')->useAttributeAsKey('type');
        $whitelistPrototype = $whitelist->arrayPrototype();
        $whitelistPrototype->scalarPrototype()->end();
        $whitelistPrototype->end();
        $whitelist->end();
        $sorting->end();

        $write = $children->arrayNode('write')->addDefaultsIfNotSet();
        $writeChildren = $write->children();

        $writeChildren->booleanNode('allow_relationship_writes')->defaultFalse()->end();
        $clientGeneratedIds = $writeChildren->arrayNode('client_generated_ids')->useAttributeAsKey('type');
        $clientGeneratedIds->booleanPrototype()->end();
        $clientGeneratedIds->defaultValue([]);
        $clientGeneratedIds->end();
        $write->end();

        $relationships = $children->arrayNode('relationships')->addDefaultsIfNotSet();
        $relationshipsChildren = $relationships->children();

        $relationshipsChildren->enumNode('write_response')->values(['linkage', '204'])->defaultValue('linkage')->end();
        $relationshipsChildren->enumNode('linkage_in_resource')->values(['never', 'when_included', 'always'])->defaultValue('when_included')->end();
        $relationships->end();

        $errors = $children->arrayNode('errors')->addDefaultsIfNotSet();
        $errorsChildren = $errors->children();
        $errorsChildren->booleanNode('expose_debug_meta')->defaultFalse()->end();
        $errorsChildren->booleanNode('add_correlation_id')->defaultTrue()->end();
        $errorsChildren->booleanNode('default_title_map')->defaultTrue()->end();
        $errorsChildren->scalarNode('locale')->defaultNull()->end();
        $errors->end();

        return $treeBuilder;
    }
}
