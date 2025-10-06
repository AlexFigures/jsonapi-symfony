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

        $this->addCacheSection($children);
        $this->addLimitsSection($children);
        $this->addPerformanceSection($children);

        return $treeBuilder;
    }

    private function addCacheSection(NodeBuilder $root): void
    {
        $cache = $root->arrayNode('cache')->addDefaultsIfNotSet();
        $cacheChildren = $cache->children();
        $cacheChildren->booleanNode('enabled')->defaultTrue()->end();

        $etag = $cacheChildren->arrayNode('etag')->addDefaultsIfNotSet();
        $etagChildren = $etag->children();
        $etagChildren->enumNode('strategy')->values(['hash', 'version'])->defaultValue('hash')->end();
        $etagChildren->scalarNode('hash_algo')->defaultValue('xxh3')->end();
        $etagChildren->booleanNode('weak_for_collections')->defaultTrue()->end();
        $etagChildren->booleanNode('include_query_shape')->defaultTrue()->end();
        $etag->end();

        $lastModified = $cacheChildren->arrayNode('last_modified')->addDefaultsIfNotSet();
        $lastModifiedChildren = $lastModified->children();
        $lastModifiedChildren->scalarNode('resource_field')->defaultValue('updatedAt')->end();
        $lastModifiedChildren->arrayNode('per_type')->useAttributeAsKey('type')->scalarPrototype()->end()->defaultValue([])->end();
        $lastModifiedChildren->booleanNode('collections_max_of')->defaultTrue()->end();
        $lastModified->end();

        $headers = $cacheChildren->arrayNode('headers')->addDefaultsIfNotSet();
        $headersChildren = $headers->children();
        $headersChildren->booleanNode('public')->defaultTrue()->end();
        $headersChildren->integerNode('max_age')->defaultValue(30)->min(0)->end();
        $headersChildren->integerNode('s_maxage')->defaultValue(600)->min(0)->end();
        $headersChildren->integerNode('stale_while_revalidate')->defaultValue(60)->min(0)->end();
        $headersChildren->integerNode('stale_if_error')->defaultValue(300)->min(0)->end();
        $headersChildren->booleanNode('add_age')->defaultTrue()->end();
        $headers->end();

        $vary = $cacheChildren->arrayNode('vary')->addDefaultsIfNotSet();
        $varyChildren = $vary->children();
        $varyChildren->booleanNode('accept')->defaultTrue()->end();
        $varyChildren->booleanNode('accept_language')->defaultFalse()->end();
        $vary->end();

        $surrogate = $cacheChildren->arrayNode('surrogate_keys')->addDefaultsIfNotSet();
        $surrogateChildren = $surrogate->children();
        $surrogateChildren->booleanNode('enabled')->defaultTrue()->end();
        $surrogateChildren->scalarNode('header_name')->defaultValue('Surrogate-Key')->end();
        $format = $surrogateChildren->arrayNode('format')->addDefaultsIfNotSet();
        $formatChildren = $format->children();
        $formatChildren->scalarNode('resource')->defaultValue('{type}:{id}')->end();
        $formatChildren->scalarNode('collection')->defaultValue('{type}')->end();
        $formatChildren->scalarNode('relationship')->defaultValue('{type}:{id}:{rel}')->end();
        $format->end();
        $surrogate->end();

        $conditional = $cacheChildren->arrayNode('conditional')->addDefaultsIfNotSet();
        $conditionalChildren = $conditional->children();
        $conditionalChildren->booleanNode('enable_if_none_match')->defaultTrue()->end();
        $conditionalChildren->booleanNode('enable_if_modified_since')->defaultTrue()->end();
        $conditionalChildren->booleanNode('enable_if_match')->defaultTrue()->end();
        $conditionalChildren->booleanNode('enable_if_unmodified_since')->defaultTrue()->end();
        $conditionalChildren->booleanNode('require_if_match_on_write')->defaultTrue()->end();
        $conditional->end();
    }

    private function addLimitsSection(NodeBuilder $root): void
    {
        $limits = $root->arrayNode('limits')->addDefaultsIfNotSet();
        $limitsChildren = $limits->children();
        $limitsChildren->integerNode('include_max_depth')->defaultValue(3)->min(0)->end();
        $limitsChildren->integerNode('include_max_paths')->defaultValue(20)->min(0)->end();
        $limitsChildren->integerNode('fields_max_total')->defaultValue(120)->min(0)->end();
        $limitsChildren->integerNode('page_max_size')->defaultValue(100)->min(0)->end();
        $limitsChildren->integerNode('included_max_resources')->defaultValue(1000)->min(0)->end();
        $limitsChildren->integerNode('complexity_budget')->defaultValue(200)->min(0)->end();
        $limits->end();
    }

    private function addPerformanceSection(NodeBuilder $root): void
    {
        $performance = $root->arrayNode('performance')->addDefaultsIfNotSet();
        $performanceChildren = $performance->children();
        $doctrine = $performanceChildren->arrayNode('doctrine')->addDefaultsIfNotSet();
        $doctrineChildren = $doctrine->children();
        $doctrineChildren->booleanNode('enable_query_cache')->defaultTrue()->end();
        $doctrineChildren->scalarNode('query_cache_pool')->defaultValue('cache.app')->end();
        $doctrineChildren->booleanNode('enable_second_level_cache')->defaultFalse()->end();
        $doctrineChildren->booleanNode('hydrate_partial_by_fields')->defaultTrue()->end();
        $doctrineChildren->enumNode('default_fetch')->values(['lazy', 'eager', 'extra_lazy'])->defaultValue('lazy')->end();
        $doctrine->end();
        $performanceChildren->booleanNode('head_enabled')->defaultTrue()->end();
        $performance->end();
    }
}
