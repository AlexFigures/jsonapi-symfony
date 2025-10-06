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

        $profilesChildren->arrayNode('enabled_by_default')->scalarPrototype()->end()->defaultValue([]);

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
}
