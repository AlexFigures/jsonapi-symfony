<?php

declare(strict_types=1);

use AlexFigures\Symfony\Bridge\Doctrine\Flush\FlushManager;
use AlexFigures\Symfony\Bridge\Symfony\EventListener\WriteListener;
use AlexFigures\Symfony\Bridge\Symfony\EventSubscriber\CachePreconditionsSubscriber;
use AlexFigures\Symfony\Bridge\Symfony\EventSubscriber\ContentNegotiationSubscriber;
use AlexFigures\Symfony\Bridge\Symfony\EventSubscriber\MediaChannelSubscriber;
use AlexFigures\Symfony\Bridge\Symfony\EventSubscriber\ProfileNegotiationSubscriber;
use AlexFigures\Symfony\Bridge\Symfony\Negotiation\ChannelScopeMatcher;
use AlexFigures\Symfony\Bridge\Symfony\Negotiation\ConfigMediaTypePolicyProvider;
use AlexFigures\Symfony\Http\Controller\CollectionController;
use AlexFigures\Symfony\Http\Controller\CreateResourceController;
use AlexFigures\Symfony\Http\Controller\DeleteResourceController;
use AlexFigures\Symfony\Docs\OpenApi\OpenApiSpecGenerator;
use AlexFigures\Symfony\Http\Controller\RelatedController;
use AlexFigures\Symfony\Http\Controller\RelationshipGetController;
use AlexFigures\Symfony\Http\Controller\RelationshipWriteController;
use AlexFigures\Symfony\Http\Controller\ResourceController;
use AlexFigures\Symfony\Http\Controller\UpdateResourceController;
use AlexFigures\Symfony\Http\Controller\OpenApiController;
use AlexFigures\Symfony\Http\Document\DocumentBuilder;
use AlexFigures\Symfony\Http\Error\CorrelationIdProvider;
use AlexFigures\Symfony\Http\Error\ErrorBuilder;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Error\JsonApiExceptionListener;
use AlexFigures\Symfony\Http\Cache\CacheKeyBuilder;
use AlexFigures\Symfony\Http\Cache\ConditionalRequestEvaluator;
use AlexFigures\Symfony\Http\Cache\EtagGeneratorInterface;
use AlexFigures\Symfony\Http\Cache\HashEtagGenerator;
use AlexFigures\Symfony\Http\Cache\HeadersApplier;
use AlexFigures\Symfony\Http\Cache\LastModifiedResolver;
use AlexFigures\Symfony\Http\Cache\SurrogateKeyBuilder;
use AlexFigures\Symfony\Http\Cache\VersionEtagGenerator;
use AlexFigures\Symfony\Http\Link\LinkGenerator;
use AlexFigures\Symfony\Http\Safety\LimitsEnforcer;
use AlexFigures\Symfony\Http\Safety\RequestComplexityScorer;
use AlexFigures\Symfony\Http\Negotiation\MediaTypeNegotiator;
use AlexFigures\Symfony\Http\Negotiation\MediaTypePolicyProviderInterface;
use AlexFigures\Symfony\Http\Request\PaginationConfig;
use AlexFigures\Symfony\Http\Request\QueryParser;
use AlexFigures\Symfony\Http\Request\SortingWhitelist;
use AlexFigures\Symfony\Http\Request\FilteringWhitelist;
use AlexFigures\Symfony\Filter\Handler\Registry\FilterHandlerRegistry;
use AlexFigures\Symfony\Filter\Handler\Registry\SortHandlerRegistry;
use AlexFigures\Symfony\Http\Validation\ConstraintViolationMapper;
use AlexFigures\Symfony\Http\Relationship\LinkageBuilder;
use AlexFigures\Symfony\Http\Relationship\WriteRelationshipsResponseConfig;
use AlexFigures\Symfony\Http\Write\ChangeSetFactory;
use AlexFigures\Symfony\Http\Write\InputDocumentValidator;
use AlexFigures\Symfony\Http\Write\RelationshipDocumentValidator;
use AlexFigures\Symfony\Http\Write\WriteConfig;
use AlexFigures\Symfony\Profile\Builtin\AuditTrailProfile;
use AlexFigures\Symfony\Profile\Builtin\RelationshipCountsProfile;
use AlexFigures\Symfony\Profile\Builtin\SoftDeleteProfile;
use AlexFigures\Symfony\Profile\Negotiation\ProfileNegotiator;
use AlexFigures\Symfony\Profile\ProfileRegistry;
use AlexFigures\Symfony\Resource\Registry\CustomRouteRegistryInterface;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistry;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use AlexFigures\Symfony\Invalidation\InvalidationDispatcher;
use AlexFigures\Symfony\Invalidation\NullPurger;
use AlexFigures\Symfony\Invalidation\SurrogatePurgerInterface;
use AlexFigures\Symfony\Contract\Data\ExistenceChecker;
use AlexFigures\Symfony\Contract\Data\RelationshipReader;
use AlexFigures\Symfony\Contract\Data\RelationshipUpdater;
use AlexFigures\Symfony\Contract\Data\ResourceProcessor;
use AlexFigures\Symfony\Contract\Data\ResourceRepository;
use AlexFigures\Symfony\Contract\Tx\TransactionManager;
use AlexFigures\Symfony\Filter\Compiler\Doctrine\DoctrineFilterCompiler;
use AlexFigures\Symfony\Filter\Operator\BetweenOperator;
use AlexFigures\Symfony\Filter\Operator\EqualOperator;
use AlexFigures\Symfony\Filter\Operator\GreaterOrEqualOperator;
use AlexFigures\Symfony\Filter\Operator\GreaterThanOperator;
use AlexFigures\Symfony\Filter\Operator\InOperator;
use AlexFigures\Symfony\Filter\Operator\NotInOperator;
use AlexFigures\Symfony\Filter\Operator\IsNullOperator;
use AlexFigures\Symfony\Filter\Operator\LessOrEqualOperator;
use AlexFigures\Symfony\Filter\Operator\LessThanOperator;
use AlexFigures\Symfony\Filter\Operator\LikeOperator;
use AlexFigures\Symfony\Filter\Operator\NotEqualOperator;
use AlexFigures\Symfony\Filter\Operator\Registry;
use AlexFigures\Symfony\Filter\Parser\FilterParser;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->set(ChannelScopeMatcher::class);

    $services
        ->set(ConfigMediaTypePolicyProvider::class)
        ->args([
            '%jsonapi.media_types%',
            service(ChannelScopeMatcher::class),
        ])
    ;

    $services->alias(MediaTypePolicyProviderInterface::class, ConfigMediaTypePolicyProvider::class);

    $services
        ->set(ContentNegotiationSubscriber::class)
        ->args([
            '%jsonapi.strict_content_negotiation%',
            service(MediaTypePolicyProviderInterface::class),
        ])
        ->tag('kernel.event_subscriber')
    ;

    $services
        ->set(MediaChannelSubscriber::class)
        ->tag('kernel.event_subscriber')
    ;

    $services->set(RequestComplexityScorer::class);

    $services
        ->set(LimitsEnforcer::class)
        ->args([
            service(ErrorMapper::class),
            service(RequestComplexityScorer::class),
            '%jsonapi.limits%',
        ])
    ;

    $services
        ->set(CacheKeyBuilder::class)
        ->args([
            '%jsonapi.cache%',
        ])
    ;

    $services
        ->set(HashEtagGenerator::class)
        ->args([
            '%jsonapi.cache%',
        ])
    ;

    $services->set(VersionEtagGenerator::class);

    $services->alias(EtagGeneratorInterface::class, HashEtagGenerator::class);

    $services->set(LastModifiedResolver::class);

    $services
        ->set(ConditionalRequestEvaluator::class)
        ->args([
            service(ErrorMapper::class),
            '%jsonapi.cache%',
        ])
    ;

    $services
        ->set(HeadersApplier::class)
        ->args([
            '%jsonapi.cache%',
        ])
    ;

    $services
        ->set(SurrogateKeyBuilder::class)
        ->args([
            '%jsonapi.cache%',
        ])
    ;

    $services->set(NullPurger::class);
    $services->alias(SurrogatePurgerInterface::class, NullPurger::class);

    $services
        ->set(InvalidationDispatcher::class)
        ->args([
            service(SurrogatePurgerInterface::class),
        ])
    ;

    $services
        ->set(CachePreconditionsSubscriber::class)
        ->args([
            '%jsonapi.cache%',
            service(CacheKeyBuilder::class),
            service(EtagGeneratorInterface::class),
            service(LastModifiedResolver::class),
            service(ConditionalRequestEvaluator::class),
            service(HeadersApplier::class),
            service(SurrogateKeyBuilder::class),
        ])
        ->tag('kernel.event_subscriber')
    ;

    $services
        ->set(ProfileRegistry::class)
        ->args([
            tagged_iterator('jsonapi.profile'),
        ])
    ;

    $services
        ->set(ProfileNegotiator::class)
        ->args([
            service(ProfileRegistry::class),
            '%jsonapi.profiles.enabled_by_default%',
            '%jsonapi.profiles.per_type%',
            '%jsonapi.profiles.negotiation%',
        ])
    ;

    $services
        ->set(ProfileNegotiationSubscriber::class)
        ->args([
            service(ProfileNegotiator::class),
        ])
        ->tag('kernel.event_subscriber')
    ;

    $services
        ->set(SoftDeleteProfile::class)
        ->args([
            '%jsonapi.profiles.soft_delete%',
        ])
        ->tag('jsonapi.profile')
    ;

    $services
        ->set(AuditTrailProfile::class)
        ->args([
            '%jsonapi.profiles.audit_trail%',
        ])
        ->tag('jsonapi.profile')
    ;

    $services
        ->set(RelationshipCountsProfile::class)
        ->args([
            '%jsonapi.profiles.rel_counts%',
        ])
        ->tag('jsonapi.profile')
    ;

    $services
        ->set(ErrorBuilder::class)
        ->args([
            '%jsonapi.errors.default_title_map%',
        ])
    ;

    $services
        ->set(ErrorMapper::class)
        ->args([
            service(ErrorBuilder::class),
        ])
    ;

    $services->set(CorrelationIdProvider::class);

    $services
        ->set(JsonApiExceptionListener::class)
        ->args([
            service(ErrorMapper::class),
            service(CorrelationIdProvider::class),
            '%jsonapi.errors.expose_debug_meta%',
            '%jsonapi.errors.add_correlation_id%',
        ])
        ->tag('kernel.event_subscriber')
    ;

    $services
        ->set(ResourceRegistry::class)
        ->args([
            tagged_iterator('jsonapi.resource', 'type'),
        ])
    ;

    $services->alias(ResourceRegistryInterface::class, ResourceRegistry::class);

    // Custom route registry
    $services
        ->set(\AlexFigures\Symfony\Resource\Registry\CustomRouteRegistry::class)
        ->args([
            [], // Will be replaced by ResourceDiscoveryPass
        ])
    ;

    $services
        ->alias(\AlexFigures\Symfony\Resource\Registry\CustomRouteRegistryInterface::class, \AlexFigures\Symfony\Resource\Registry\CustomRouteRegistry::class)
    ;

    $services
        ->set(PaginationConfig::class)
        ->args([
            '%jsonapi.pagination.default_size%',
            '%jsonapi.pagination.max_size%',
        ])
    ;

    $services
        ->set(SortingWhitelist::class)
        ->args([
            service(ResourceRegistryInterface::class),
        ])
    ;

    $services
        ->set(FilteringWhitelist::class)
        ->args([
            service(ResourceRegistryInterface::class),
            service(ErrorMapper::class),
        ])
    ;

    $services->set(FilterParser::class);

    $services
        ->set(QueryParser::class)
        ->args([
            service(ResourceRegistryInterface::class),
            service(PaginationConfig::class),
            service(SortingWhitelist::class),
            service(FilteringWhitelist::class),
            service(ErrorMapper::class),
            service(FilterParser::class),
            service(LimitsEnforcer::class),
        ])
    ;

    $services
        ->set(PropertyAccessorInterface::class)
        ->factory([PropertyAccess::class, 'createPropertyAccessor'])
    ;

    $services
        ->set(LinkGenerator::class)
        ->args([
            service(UrlGeneratorInterface::class),
        ])
    ;

    $services
        ->set(DocumentBuilder::class)
        ->args([
            service(ResourceRegistryInterface::class),
            service(PropertyAccessorInterface::class),
            service(LinkGenerator::class),
            '%jsonapi.relationships.linkage_in_resource%',
            service(LimitsEnforcer::class),
        ])
    ;

    $services
        ->set(OpenApiSpecGenerator::class)
        ->args([
            service(ResourceRegistryInterface::class),
            service(CustomRouteRegistryInterface::class),
            '%jsonapi.docs.generator.openapi%',
            '%jsonapi.route_prefix%',
            '%jsonapi.relationships.write_response%',
        ])
    ;

    $services
        ->set(OpenApiController::class)
        ->args([
            service(OpenApiSpecGenerator::class),
            '%jsonapi.docs.generator.openapi%',
        ])
        ->tag('controller.service_arguments')
    ;

    $services
        ->set(\AlexFigures\Symfony\Http\Controller\SwaggerUiController::class)
        ->args([
            '%jsonapi.docs.ui%',
        ])
        ->tag('controller.service_arguments')
    ;

    $services
        ->set(LinkageBuilder::class)
        ->args([
            service(ResourceRegistryInterface::class),
            service(RelationshipReader::class),
            service(PaginationConfig::class),
        ])
    ;

    $services
        ->set(WriteRelationshipsResponseConfig::class)
        ->args([
            '%jsonapi.relationships.write_response%',
        ])
    ;

    $services
        ->set(RelationshipDocumentValidator::class)
        ->args([
            service(ResourceRegistryInterface::class),
            service(ExistenceChecker::class),
            service(ErrorMapper::class),
        ])
    ;

    $services
        ->set(WriteConfig::class)
        ->args([
            '%jsonapi.write.allow_relationship_writes%',
            '%jsonapi.write.client_generated_ids%',
        ])
    ;

    $services
        ->set(InputDocumentValidator::class)
        ->args([
            service(ResourceRegistryInterface::class),
            service(WriteConfig::class),
            service(ErrorMapper::class),
        ])
    ;

    $services
        ->set(ConstraintViolationMapper::class)
        ->args([
            service(ResourceRegistryInterface::class),
            service(ErrorMapper::class),
        ])
    ;

    $services
        ->set(ChangeSetFactory::class)
        ->args([
            service(ResourceRegistryInterface::class),
        ])
    ;

    $services
        ->set(CollectionController::class)
        ->autowire()
        ->autoconfigure()
        ->tag('controller.service_arguments')
    ;

    $services
        ->set(ResourceController::class)
        ->autowire()
        ->autoconfigure()
        ->tag('controller.service_arguments')
    ;

    $services
        ->set(CreateResourceController::class)
        ->autowire()
        ->autoconfigure()
        ->tag('controller.service_arguments')
    ;

    $services
        ->set(UpdateResourceController::class)
        ->autowire()
        ->autoconfigure()
        ->tag('controller.service_arguments')
    ;

    $services
        ->set(DeleteResourceController::class)
        ->autowire()
        ->autoconfigure()
        ->tag('controller.service_arguments')
    ;

    $services
        ->set(RelatedController::class)
        ->autowire()
        ->autoconfigure()
        ->tag('controller.service_arguments')
    ;

    $services
        ->set(RelationshipGetController::class)
        ->autowire()
        ->autoconfigure()
        ->tag('controller.service_arguments')
    ;

    $services
        ->set(RelationshipWriteController::class)
        ->autowire()
        ->autoconfigure()
        ->tag('controller.service_arguments')
    ;

    // Route name generator
    $services
        ->set(\AlexFigures\Symfony\Bridge\Symfony\Routing\RouteNameGenerator::class)
        ->args([
            '%jsonapi.routing.naming_convention%',
        ])
    ;

    // Automatic route loader
    $services
        ->set(\AlexFigures\Symfony\Bridge\Symfony\Routing\JsonApiRouteLoader::class)
        ->args([
            service(ResourceRegistry::class),
            '%jsonapi.route_prefix%',
            true, // enableRelationshipRoutes
            '%jsonapi.docs.generator.openapi%',
            '%jsonapi.docs.ui%',
            service(\AlexFigures\Symfony\Bridge\Symfony\Routing\RouteNameGenerator::class),
            service(\AlexFigures\Symfony\Resource\Registry\CustomRouteRegistry::class),
        ])
        ->tag('routing.loader')
    ;

    // NullObject implementations for optional dependencies
    // Registered with low priority so users can override them

    $services
        ->set('jsonapi.null_existence_checker', \AlexFigures\Symfony\Contract\Data\NullExistenceChecker::class)
    ;

    $services
        ->alias(ExistenceChecker::class, 'jsonapi.null_existence_checker')
    ;

    $services
        ->set('jsonapi.null_relationship_reader', \AlexFigures\Symfony\Contract\Data\NullRelationshipReader::class)
    ;

    $services
        ->alias(RelationshipReader::class, 'jsonapi.null_relationship_reader')
    ;

    $services
        ->set('jsonapi.null_relationship_updater', \AlexFigures\Symfony\Contract\Data\NullRelationshipUpdater::class)
    ;

    $services
        ->alias(RelationshipUpdater::class, 'jsonapi.null_relationship_updater')
    ;

    $services
        ->set('jsonapi.null_resource_processor', \AlexFigures\Symfony\Bridge\Symfony\Null\NullResourceProcessor::class)
    ;

    $services
        ->alias(ResourceProcessor::class, 'jsonapi.null_resource_processor')
    ;

    $services
        ->set('jsonapi.null_resource_repository', \AlexFigures\Symfony\Contract\Data\NullResourceRepository::class)
    ;

    $services
        ->alias(ResourceRepository::class, 'jsonapi.null_resource_repository')
    ;

    $services
        ->set('jsonapi.null_transaction_manager', \AlexFigures\Symfony\Contract\Tx\NullTransactionManager::class)
    ;

    $services
        ->alias(TransactionManager::class, 'jsonapi.null_transaction_manager')
    ;

    // Filter operators
    $services->set(EqualOperator::class)->tag('jsonapi.filter.operator');
    $services->set(NotEqualOperator::class)->tag('jsonapi.filter.operator');
    $services->set(LessThanOperator::class)->tag('jsonapi.filter.operator');
    $services->set(LessOrEqualOperator::class)->tag('jsonapi.filter.operator');
    $services->set(GreaterThanOperator::class)->tag('jsonapi.filter.operator');
    $services->set(GreaterOrEqualOperator::class)->tag('jsonapi.filter.operator');
    $services->set(LikeOperator::class)->tag('jsonapi.filter.operator');
    $services->set(InOperator::class)->tag('jsonapi.filter.operator');
    $services->set(NotInOperator::class)->tag('jsonapi.filter.operator');
    $services->set(IsNullOperator::class)->tag('jsonapi.filter.operator');
    $services->set(BetweenOperator::class)->tag('jsonapi.filter.operator');

    // Filter operator registry
    $services
        ->set(Registry::class)
        ->args([
            tagged_iterator('jsonapi.filter.operator'),
        ])
    ;

    // Filter handler registry
    $services
        ->set(FilterHandlerRegistry::class)
        ->args([
            tagged_iterator('jsonapi.filter.handler'),
        ])
    ;

    // Sort handler registry
    $services
        ->set(SortHandlerRegistry::class)
        ->args([
            tagged_iterator('jsonapi.sort.handler'),
        ])
    ;

    // Filter compiler
    $services
        ->set(DoctrineFilterCompiler::class)
        ->args([
            service(Registry::class),
            service(FilterHandlerRegistry::class),
        ])
    ;

    // Doctrine Bridge Services
    // These are registered here so users don't have to manually configure them
    // They will be used when data_layer.provider is set to 'doctrine' (default)

    // SerializerEntityInstantiator - uses the Symfony Serializer to instantiate entities
    // Mirrors the approach used by API Platform
    $services
        ->set(\AlexFigures\Symfony\Bridge\Doctrine\Instantiator\SerializerEntityInstantiator::class)
        ->args([
            service('doctrine.orm.default_entity_manager'),
            service(PropertyAccessorInterface::class),
        ])
    ;

    $services
        ->set(\AlexFigures\Symfony\Resource\Mapper\DefaultReadMapper::class)
        ->autowire()
        ->autoconfigure();

    $services->alias(
        \AlexFigures\Symfony\Resource\Mapper\ReadMapperInterface::class,
        \AlexFigures\Symfony\Resource\Mapper\DefaultReadMapper::class
    )->public(false);

    $services
        ->set(\AlexFigures\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository::class)
        ->args([
            service('doctrine.orm.default_entity_manager'),
            service(ResourceRegistryInterface::class),
            service(DoctrineFilterCompiler::class),
            service(SortHandlerRegistry::class),
            service(\AlexFigures\Symfony\Resource\Mapper\ReadMapperInterface::class),
        ])
    ;

    $services
        ->set(\AlexFigures\Symfony\Http\Validation\DatabaseErrorMapper::class)
        ->args([
            service(ResourceRegistryInterface::class),
            service(\AlexFigures\Symfony\Http\Error\ErrorMapper::class),
        ])
    ;

    $services
        ->set(\AlexFigures\Symfony\Resource\Relationship\RelationshipResolver::class)
        ->args([
            service('doctrine.orm.default_entity_manager'),
            service(ResourceRegistryInterface::class),
            service(PropertyAccessorInterface::class),
            service(ErrorMapper::class),
        ])
    ;

    // FlushManager - centralized flush control
    $services
        ->set(FlushManager::class)
        ->args([
            service('doctrine.orm.default_entity_manager'),
        ])
    ;

    // WriteListener - automatically flushes after write operations
    $services
        ->set(WriteListener::class)
        ->args([
            service(FlushManager::class),
            service(\AlexFigures\Symfony\Http\Validation\DatabaseErrorMapper::class),
        ])
        ->tag('kernel.event_subscriber')
    ;

    $services
        ->set(\AlexFigures\Symfony\Bridge\Doctrine\Persister\ValidatingDoctrineProcessor::class)
        ->args([
            service('doctrine.orm.default_entity_manager'),
            service(ResourceRegistryInterface::class),
            service(PropertyAccessorInterface::class),
            service('validator'),
            service(ConstraintViolationMapper::class),
            service(\AlexFigures\Symfony\Bridge\Doctrine\Instantiator\SerializerEntityInstantiator::class),
            service(\AlexFigures\Symfony\Resource\Relationship\RelationshipResolver::class),
            service(FlushManager::class),
        ])
    ;

    $services
        ->set(\AlexFigures\Symfony\Bridge\Serializer\Normalizer\JsonApiRelationshipDenormalizer::class)
        ->args([
            service(\AlexFigures\Symfony\Resource\Relationship\RelationshipResolver::class),
            service(ResourceRegistryInterface::class),
        ])
        ->tag('serializer.normalizer', ['priority' => 100]) // High priority to handle relationships first
    ;

    $services
        ->set(\AlexFigures\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler::class)
        ->args([
            service('doctrine.orm.default_entity_manager'),
            service(ResourceRegistryInterface::class),
            service(PropertyAccessorInterface::class),
        ])
    ;

    $services
        ->set(\AlexFigures\Symfony\Bridge\Doctrine\Transaction\DoctrineTransactionManager::class)
        ->args([
            service('doctrine.orm.default_entity_manager'),
        ])
    ;

    $services
        ->set(\AlexFigures\Symfony\Bridge\Doctrine\ExistenceChecker\DoctrineExistenceChecker::class)
        ->args([
            service('doctrine.orm.default_entity_manager'),
            service(ResourceRegistryInterface::class),
        ])
    ;
};
