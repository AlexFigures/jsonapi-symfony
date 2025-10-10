<?php

declare(strict_types=1);

use JsonApi\Symfony\Bridge\Doctrine\Flush\FlushManager;
use JsonApi\Symfony\Bridge\Symfony\EventListener\WriteListener;
use JsonApi\Symfony\Bridge\Symfony\EventSubscriber\CachePreconditionsSubscriber;
use JsonApi\Symfony\Bridge\Symfony\EventSubscriber\ContentNegotiationSubscriber;
use JsonApi\Symfony\Bridge\Symfony\EventSubscriber\ProfileNegotiationSubscriber;
use JsonApi\Symfony\Http\Controller\CollectionController;
use JsonApi\Symfony\Http\Controller\CreateResourceController;
use JsonApi\Symfony\Http\Controller\DeleteResourceController;
use JsonApi\Symfony\Docs\OpenApi\OpenApiSpecGenerator;
use JsonApi\Symfony\Http\Controller\RelatedController;
use JsonApi\Symfony\Http\Controller\RelationshipGetController;
use JsonApi\Symfony\Http\Controller\RelationshipWriteController;
use JsonApi\Symfony\Http\Controller\ResourceController;
use JsonApi\Symfony\Http\Controller\UpdateResourceController;
use JsonApi\Symfony\Http\Controller\OpenApiController;
use JsonApi\Symfony\Http\Document\DocumentBuilder;
use JsonApi\Symfony\Http\Error\CorrelationIdProvider;
use JsonApi\Symfony\Http\Error\ErrorBuilder;
use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Error\JsonApiExceptionListener;
use JsonApi\Symfony\Http\Cache\CacheKeyBuilder;
use JsonApi\Symfony\Http\Cache\ConditionalRequestEvaluator;
use JsonApi\Symfony\Http\Cache\EtagGeneratorInterface;
use JsonApi\Symfony\Http\Cache\HashEtagGenerator;
use JsonApi\Symfony\Http\Cache\HeadersApplier;
use JsonApi\Symfony\Http\Cache\LastModifiedResolver;
use JsonApi\Symfony\Http\Cache\SurrogateKeyBuilder;
use JsonApi\Symfony\Http\Cache\VersionEtagGenerator;
use JsonApi\Symfony\Http\Link\LinkGenerator;
use JsonApi\Symfony\Http\Safety\LimitsEnforcer;
use JsonApi\Symfony\Http\Safety\RequestComplexityScorer;
use JsonApi\Symfony\Http\Negotiation\MediaTypeNegotiator;
use JsonApi\Symfony\Http\Request\PaginationConfig;
use JsonApi\Symfony\Http\Request\QueryParser;
use JsonApi\Symfony\Http\Request\SortingWhitelist;
use JsonApi\Symfony\Http\Request\FilteringWhitelist;
use JsonApi\Symfony\Filter\Handler\Registry\FilterHandlerRegistry;
use JsonApi\Symfony\Filter\Handler\Registry\SortHandlerRegistry;
use JsonApi\Symfony\Http\Validation\ConstraintViolationMapper;
use JsonApi\Symfony\Http\Relationship\LinkageBuilder;
use JsonApi\Symfony\Http\Relationship\WriteRelationshipsResponseConfig;
use JsonApi\Symfony\Http\Write\ChangeSetFactory;
use JsonApi\Symfony\Http\Write\InputDocumentValidator;
use JsonApi\Symfony\Http\Write\RelationshipDocumentValidator;
use JsonApi\Symfony\Http\Write\WriteConfig;
use JsonApi\Symfony\Profile\Builtin\AuditTrailProfile;
use JsonApi\Symfony\Profile\Builtin\RelationshipCountsProfile;
use JsonApi\Symfony\Profile\Builtin\SoftDeleteProfile;
use JsonApi\Symfony\Profile\Negotiation\ProfileNegotiator;
use JsonApi\Symfony\Profile\ProfileRegistry;
use JsonApi\Symfony\Resource\Registry\CustomRouteRegistryInterface;
use JsonApi\Symfony\Resource\Registry\ResourceRegistry;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use JsonApi\Symfony\Invalidation\InvalidationDispatcher;
use JsonApi\Symfony\Invalidation\NullPurger;
use JsonApi\Symfony\Invalidation\SurrogatePurgerInterface;
use JsonApi\Symfony\Contract\Data\ExistenceChecker;
use JsonApi\Symfony\Contract\Data\RelationshipReader;
use JsonApi\Symfony\Contract\Data\RelationshipUpdater;
use JsonApi\Symfony\Contract\Data\ResourceProcessor;
use JsonApi\Symfony\Contract\Data\ResourceRepository;
use JsonApi\Symfony\Contract\Tx\TransactionManager;
use JsonApi\Symfony\Filter\Compiler\Doctrine\DoctrineFilterCompiler;
use JsonApi\Symfony\Filter\Operator\BetweenOperator;
use JsonApi\Symfony\Filter\Operator\EqualOperator;
use JsonApi\Symfony\Filter\Operator\GreaterOrEqualOperator;
use JsonApi\Symfony\Filter\Operator\GreaterThanOperator;
use JsonApi\Symfony\Filter\Operator\InOperator;
use JsonApi\Symfony\Filter\Operator\NotInOperator;
use JsonApi\Symfony\Filter\Operator\IsNullOperator;
use JsonApi\Symfony\Filter\Operator\LessOrEqualOperator;
use JsonApi\Symfony\Filter\Operator\LessThanOperator;
use JsonApi\Symfony\Filter\Operator\LikeOperator;
use JsonApi\Symfony\Filter\Operator\NotEqualOperator;
use JsonApi\Symfony\Filter\Operator\Registry;
use JsonApi\Symfony\Filter\Parser\FilterParser;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services
        ->set(ContentNegotiationSubscriber::class)
        ->args([
            '%jsonapi.strict_content_negotiation%',
            '%jsonapi.media_type%',
        ])
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
        ->set(\JsonApi\Symfony\Resource\Registry\CustomRouteRegistry::class)
        ->args([
            [], // Will be replaced by ResourceDiscoveryPass
        ])
    ;

    $services
        ->alias(\JsonApi\Symfony\Resource\Registry\CustomRouteRegistryInterface::class, \JsonApi\Symfony\Resource\Registry\CustomRouteRegistry::class)
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
        ->set(\JsonApi\Symfony\Http\Controller\SwaggerUiController::class)
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
        ->set(\JsonApi\Symfony\Bridge\Symfony\Routing\RouteNameGenerator::class)
        ->args([
            '%jsonapi.routing.naming_convention%',
        ])
    ;

    // Automatic route loader
    $services
        ->set(\JsonApi\Symfony\Bridge\Symfony\Routing\JsonApiRouteLoader::class)
        ->args([
            service(ResourceRegistry::class),
            '%jsonapi.route_prefix%',
            true, // enableRelationshipRoutes
            '%jsonapi.docs.generator.openapi%',
            '%jsonapi.docs.ui%',
            service(\JsonApi\Symfony\Bridge\Symfony\Routing\RouteNameGenerator::class),
            service(\JsonApi\Symfony\Resource\Registry\CustomRouteRegistry::class),
        ])
        ->tag('routing.loader')
    ;

    // NullObject implementations for optional dependencies
    // Registered with low priority so users can override them

    $services
        ->set('jsonapi.null_existence_checker', \JsonApi\Symfony\Contract\Data\NullExistenceChecker::class)
    ;

    $services
        ->alias(ExistenceChecker::class, 'jsonapi.null_existence_checker')
    ;

    $services
        ->set('jsonapi.null_relationship_reader', \JsonApi\Symfony\Contract\Data\NullRelationshipReader::class)
    ;

    $services
        ->alias(RelationshipReader::class, 'jsonapi.null_relationship_reader')
    ;

    $services
        ->set('jsonapi.null_relationship_updater', \JsonApi\Symfony\Contract\Data\NullRelationshipUpdater::class)
    ;

    $services
        ->alias(RelationshipUpdater::class, 'jsonapi.null_relationship_updater')
    ;

    $services
        ->set('jsonapi.null_resource_processor', \JsonApi\Symfony\Bridge\Symfony\Null\NullResourceProcessor::class)
    ;

    $services
        ->alias(ResourceProcessor::class, 'jsonapi.null_resource_processor')
    ;

    $services
        ->set('jsonapi.null_resource_repository', \JsonApi\Symfony\Contract\Data\NullResourceRepository::class)
    ;

    $services
        ->alias(ResourceRepository::class, 'jsonapi.null_resource_repository')
    ;

    $services
        ->set('jsonapi.null_transaction_manager', \JsonApi\Symfony\Contract\Tx\NullTransactionManager::class)
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
        ->set(\JsonApi\Symfony\Bridge\Doctrine\Instantiator\SerializerEntityInstantiator::class)
        ->args([
            service('doctrine.orm.default_entity_manager'),
            service(PropertyAccessorInterface::class),
        ])
    ;

    $services
        ->set(\JsonApi\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository::class)
        ->args([
            service('doctrine.orm.default_entity_manager'),
            service(ResourceRegistryInterface::class),
            service(DoctrineFilterCompiler::class),
            service(SortHandlerRegistry::class),
        ])
    ;

    $services
        ->set(\JsonApi\Symfony\Http\Validation\DatabaseErrorMapper::class)
        ->args([
            service(ResourceRegistryInterface::class),
            service(\JsonApi\Symfony\Http\Error\ErrorMapper::class),
        ])
    ;

    $services
        ->set(\JsonApi\Symfony\Resource\Relationship\RelationshipResolver::class)
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
            service(\JsonApi\Symfony\Http\Validation\DatabaseErrorMapper::class),
        ])
        ->tag('kernel.event_subscriber')
    ;

    $services
        ->set(\JsonApi\Symfony\Bridge\Doctrine\Persister\ValidatingDoctrineProcessor::class)
        ->args([
            service('doctrine.orm.default_entity_manager'),
            service(ResourceRegistryInterface::class),
            service(PropertyAccessorInterface::class),
            service('validator'),
            service(ConstraintViolationMapper::class),
            service(\JsonApi\Symfony\Bridge\Doctrine\Instantiator\SerializerEntityInstantiator::class),
            service(\JsonApi\Symfony\Resource\Relationship\RelationshipResolver::class),
            service(FlushManager::class),
        ])
    ;

    $services
        ->set(\JsonApi\Symfony\Bridge\Serializer\Normalizer\JsonApiRelationshipDenormalizer::class)
        ->args([
            service(\JsonApi\Symfony\Resource\Relationship\RelationshipResolver::class),
            service(ResourceRegistryInterface::class),
        ])
        ->tag('serializer.normalizer', ['priority' => 100]) // High priority to handle relationships first
    ;

    $services
        ->set(\JsonApi\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler::class)
        ->args([
            service('doctrine.orm.default_entity_manager'),
            service(ResourceRegistryInterface::class),
            service(PropertyAccessorInterface::class),
        ])
    ;

    $services
        ->set(\JsonApi\Symfony\Bridge\Doctrine\Transaction\DoctrineTransactionManager::class)
        ->args([
            service('doctrine.orm.default_entity_manager'),
        ])
    ;
};
