<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Symfony\DependencyInjection;

use AlexFigures\Symfony\Http\Negotiation\MediaType;
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

        // Routing configuration
        $routing = $children->arrayNode('routing')->addDefaultsIfNotSet();
        $routingChildren = $routing->children();
        $routingChildren
            ->enumNode('naming_convention')
            ->info('Route naming convention for auto-generated routes')
            ->values(['snake_case', 'kebab-case'])
            ->defaultValue('snake_case')
            ->end();
        $routing->end();

        // Resource discovery paths
        /** @var ArrayNodeDefinition $resourcePaths */
        $resourcePaths = $children->arrayNode('resource_paths');
        $resourcePaths
            ->info('Directories to scan for JSON:API resources with #[JsonApiResource] attribute')
            ->defaultValue(['%kernel.project_dir%/src/Entity'])
            ->scalarPrototype()->end();
        $resourcePaths->end();

        // Data layer configuration
        $dataLayer = $children->arrayNode('data_layer')->addDefaultsIfNotSet();
        $dataLayerChildren = $dataLayer->children();

        $dataLayerChildren
            ->enumNode('provider')
            ->info('Data layer implementation to use')
            ->values(['doctrine', 'custom'])
            ->defaultValue('doctrine')
            ->end();

        $dataLayerChildren
            ->scalarNode('repository')
            ->info('Custom ResourceRepository service ID (only when provider=custom)')
            ->defaultNull()
            ->end();

        $dataLayerChildren
            ->scalarNode('processor')
            ->info('Custom ResourceProcessor service ID (only when provider=custom)')
            ->defaultNull()
            ->end();

        $dataLayerChildren
            ->scalarNode('relationship_reader')
            ->info('Custom RelationshipReader service ID (only when provider=custom)')
            ->defaultNull()
            ->end();

        $dataLayerChildren
            ->scalarNode('transaction_manager')
            ->info('Custom TransactionManager service ID (only when provider=custom)')
            ->defaultNull()
            ->end();

        $dataLayer->end();

        $pagination = $children->arrayNode('pagination')->addDefaultsIfNotSet();
        $paginationChildren = $pagination->children();

        /** @var IntegerNodeDefinition $defaultSize */
        $defaultSize = $paginationChildren->integerNode('default_size');
        $defaultSize->min(1)->defaultValue(25)->end();

        /** @var IntegerNodeDefinition $maxSize */
        $maxSize = $paginationChildren->integerNode('max_size');
        $maxSize->min(1)->defaultValue(100)->end();

        $pagination->end();

        $write = $children->arrayNode('write')->addDefaultsIfNotSet();
        $writeChildren = $write->children();

        $writeChildren->booleanNode('allow_relationship_writes')->defaultFalse()->end();
        /** @var ArrayNodeDefinition $clientGeneratedIds */
        $clientGeneratedIds = $writeChildren->arrayNode('client_generated_ids')->useAttributeAsKey('type');
        $clientGeneratedIds->booleanPrototype()->end();
        $clientGeneratedIds->defaultValue([]);
        $clientGeneratedIds->end();
        $write->end();

        $relationships = $children->arrayNode('relationships')->addDefaultsIfNotSet();
        $relationshipsChildren = $relationships->children();

        $relationshipsChildren->enumNode('write_response')->values(['linkage', '204'])->defaultValue('linkage')->end();
        $relationshipsChildren->enumNode('linkage_in_resource')->values(['never', 'when_included', 'always'])->defaultValue('always')->end();
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
        $this->addDxSection($children);
        $this->addDocsSection($children);
        $this->addReleaseSection($children);
        $atomic = $children->arrayNode('atomic')->addDefaultsIfNotSet();
        $atomicChildren = $atomic->children();
        $atomicChildren->booleanNode('enabled')->defaultFalse()->end();
        $atomicChildren->scalarNode('endpoint')->defaultValue('/api/operations')->end();
        $atomicChildren->booleanNode('require_ext_header')->defaultTrue()->end();
        $atomicChildren->integerNode('max_operations')->min(1)->defaultValue(100)->end();
        $atomicChildren->enumNode('return_policy')->values(['auto', 'none', 'always'])->defaultValue('auto')->end();
        $atomicChildren->booleanNode('allow_href')->defaultTrue()->end();
        $lid = $atomicChildren->arrayNode('lid')->addDefaultsIfNotSet();
        $lidChildren = $lid->children();
        $lidChildren->booleanNode('accept_in_resource_and_identifier')->defaultTrue()->end();
        $lid->end();
        $atomicChildren->end();
        $profiles = $children->arrayNode('profiles')->addDefaultsIfNotSet();
        $profilesChildren = $profiles->children();

        $negotiation = $profilesChildren->arrayNode('negotiation')->addDefaultsIfNotSet();
        $negotiationChildren = $negotiation->children();
        $negotiationChildren->booleanNode('require_known_profiles')->defaultFalse()->end();
        $negotiationChildren->booleanNode('echo_profiles_in_content_type')->defaultTrue()->end();
        $negotiationChildren->booleanNode('link_header')->defaultTrue()->end();
        $negotiation->end();

        /** @var ArrayNodeDefinition $enabledByDefault */
        $enabledByDefault = $profilesChildren->arrayNode('enabled_by_default');
        $enabledByDefault->scalarPrototype()->end();
        $enabledByDefault->defaultValue([]);
        $enabledByDefault->end();

        /** @var ArrayNodeDefinition $perType */
        $perType = $profilesChildren->arrayNode('per_type')->useAttributeAsKey('type')->arrayPrototype();
        $perType->scalarPrototype()->end();
        $perType->end();

        $softDelete = $profilesChildren->arrayNode('soft_delete')->addDefaultsIfNotSet();
        $softDeleteChildren = $softDelete->children();
        $softDeleteChildren->scalarNode('field')->defaultValue('deletedAt')->end();
        $softDeleteChildren->enumNode('strategy')->values(['timestamp', 'boolean'])->defaultValue('timestamp')->end();
        $softDeleteChildren->enumNode('default_visibility')->values(['exclude', 'include', 'only'])->defaultValue('exclude')->end();
        $queryFlags = $softDeleteChildren->arrayNode('query_flags')->addDefaultsIfNotSet();
        $queryFlagsChildren = $queryFlags->children();
        $queryFlagsChildren->scalarNode('with_deleted')->defaultValue('withDeleted')->end();
        $queryFlagsChildren->scalarNode('only_deleted')->defaultValue('onlyDeleted')->end();
        $queryFlags->end();
        $softDeleteChildren->enumNode('delete_semantics')->values(['soft', 'hard'])->defaultValue('soft')->end();
        $softDelete->end();

        $audit = $profilesChildren->arrayNode('audit_trail')->addDefaultsIfNotSet();
        $auditChildren = $audit->children();
        $auditChildren->scalarNode('created_at')->defaultValue('createdAt')->end();
        $auditChildren->scalarNode('updated_at')->defaultValue('updatedAt')->end();
        $auditChildren->scalarNode('created_by')->defaultNull()->end();
        $auditChildren->scalarNode('updated_by')->defaultNull()->end();
        $auditChildren->booleanNode('expose_in_meta')->defaultTrue()->end();
        $audit->end();

        $relationshipCounts = $profilesChildren->arrayNode('rel_counts')->addDefaultsIfNotSet();
        $relationshipCountsChildren = $relationshipCounts->children();
        $relationshipCountsChildren->scalarNode('relationship_meta_key')->defaultValue('count')->end();
        $relationshipCountsChildren->booleanNode('compute_in_related_endpoints')->defaultTrue()->end();
        $relationshipCounts->end();

        $profiles->end();

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
        /** @var ArrayNodeDefinition $perTypeOverrides */
        $perTypeOverrides = $lastModifiedChildren->arrayNode('per_type')->useAttributeAsKey('type');
        $perTypeOverrides->scalarPrototype()->end();
        $perTypeOverrides->defaultValue([]);
        $perTypeOverrides->end();
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

    private function addDxSection(NodeBuilder $root): void
    {
        $dx = $root->arrayNode('dx')->addDefaultsIfNotSet();
        $dxChildren = $dx->children();

        $dxChildren->booleanNode('dev_toolbar')->defaultTrue()->end();

        $sandbox = $dxChildren->arrayNode('sandbox')->addDefaultsIfNotSet();
        $sandboxChildren = $sandbox->children();
        $sandboxChildren->booleanNode('enabled')->defaultTrue()->end();
        $sandboxChildren->scalarNode('route')->defaultValue('/_jsonapi/sandbox')->end();
        $sandbox->end();

        $doctor = $dxChildren->arrayNode('doctor')->addDefaultsIfNotSet();
        $doctorChildren = $doctor->children();
        $doctorChildren->booleanNode('enabled')->defaultTrue()->end();
        /** @var ArrayNodeDefinition $rules */
        $rules = $doctorChildren->arrayNode('rules');
        $rules->scalarPrototype()->end();
        $rules->defaultValue([
            'negotiation.vary.accept',
            'errors.listener.registered',
            'profiles.per_type.known',
            'filters.whitelist.coverage',
            'pagination.cursor.sort_key.stable',
        ]);
        $rules->end();
        $doctor->end();

        $maker = $dxChildren->arrayNode('maker')->addDefaultsIfNotSet();
        $makerChildren = $maker->children();
        $defaults = $makerChildren->arrayNode('defaults')->addDefaultsIfNotSet();
        $defaultsChildren = $defaults->children();
        $defaultsChildren->scalarNode('namespace')->defaultValue('App\\JsonApi')->end();
        $defaultsChildren->scalarNode('resource_type_prefix')->defaultValue('')->end();
        $defaults->end();
        $maker->end();

        $dx->end();
    }

    private function addDocsSection(NodeBuilder $root): void
    {
        $docs = $root->arrayNode('docs')->addDefaultsIfNotSet();
        $docsChildren = $docs->children();

        $generator = $docsChildren->arrayNode('generator')->addDefaultsIfNotSet();
        $generatorChildren = $generator->children();

        $openApi = $generatorChildren->arrayNode('openapi')->addDefaultsIfNotSet();
        $openApiChildren = $openApi->children();
        $openApiChildren->booleanNode('enabled')->defaultTrue()->end();
        $openApiChildren->scalarNode('route')->defaultValue('/_jsonapi/openapi.json')->end();
        $openApiChildren->scalarNode('title')->defaultValue('My API')->end();
        $openApiChildren->scalarNode('version')->defaultValue('1.0.0')->end();
        /** @var ArrayNodeDefinition $servers */
        $servers = $openApiChildren->arrayNode('servers');
        $servers->scalarPrototype()->end();
        $servers->defaultValue(['https://api.example.com']);
        $servers->end();
        $openApi->end();

        $ui = $docsChildren->arrayNode('ui')->addDefaultsIfNotSet();
        $uiChildren = $ui->children();
        $uiChildren->booleanNode('enabled')->defaultTrue()->end();
        $uiChildren->scalarNode('route')->defaultValue('/_jsonapi/docs')->end();
        $uiChildren->scalarNode('spec_url')->defaultValue('/_jsonapi/openapi.json')->end();
        $uiChildren->enumNode('theme')->values(['swagger', 'redoc'])->defaultValue('swagger')->end();
        $ui->end();

        $jsonSchema = $generatorChildren->arrayNode('json_schema')->addDefaultsIfNotSet();
        $jsonSchemaChildren = $jsonSchema->children();
        $jsonSchemaChildren->booleanNode('enabled')->defaultTrue()->end();
        $jsonSchemaChildren->scalarNode('route')->defaultValue('/_jsonapi/schemas')->end();
        $jsonSchemaChildren->booleanNode('include_profiles')->defaultTrue()->end();
        $jsonSchema->end();

        $generator->end();
        $docs->end();
    }

    private function addReleaseSection(NodeBuilder $root): void
    {
        $release = $root->arrayNode('release')->addDefaultsIfNotSet();
        $releaseChildren = $release->children();

        $releaseChildren->enumNode('semver')->values(['strict', 'relaxed'])->defaultValue('strict')->end();
        $releaseChildren->scalarNode('bc_policy')->defaultValue('minor-no-break')->end();
        $releaseChildren->scalarNode('min_php')->defaultValue('8.2')->end();
        $releaseChildren->scalarNode('min_symfony')->defaultValue('7.1')->end();

        $release->end();
    }
}
