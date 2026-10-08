<?php

declare(strict_types=1);

use AlexFigures\JsonApi\Bridge\Doctrine\Flush\FlushManager;
use AlexFigures\JsonApi\Bridge\Symfony\EventListener\WriteListener;
use AlexFigures\JsonApi\Bridge\Symfony\EventSubscriber\CachePreconditionsSubscriber;
use AlexFigures\JsonApi\Bridge\Symfony\EventSubscriber\ContentNegotiationSubscriber;
use AlexFigures\JsonApi\Bridge\Symfony\EventSubscriber\MediaChannelSubscriber;
use AlexFigures\JsonApi\Bridge\Symfony\EventSubscriber\ProfileNegotiationSubscriber;
use AlexFigures\JsonApi\Bridge\Symfony\Negotiation\ChannelScopeMatcher;
use AlexFigures\JsonApi\Bridge\Symfony\Negotiation\ConfigMediaTypePolicyProvider;
use AlexFigures\JsonApi\Contract\Data\ExistenceChecker;
use AlexFigures\JsonApi\Contract\Data\RelationshipReader;
use AlexFigures\JsonApi\Contract\Data\RelationshipUpdater;
use AlexFigures\JsonApi\Contract\Data\ResourceProcessor;
use AlexFigures\JsonApi\Contract\Data\ResourceRepository;
use AlexFigures\JsonApi\Contract\Tx\TransactionManager;
use AlexFigures\JsonApi\Docs\OpenApi\CustomEndpointCollector;
use AlexFigures\JsonApi\Docs\OpenApi\OpenApiSpecGenerator;
use AlexFigures\JsonApi\Filter\Compiler\Doctrine\DoctrineFilterCompiler;
use AlexFigures\JsonApi\Filter\Handler\Registry\FilterHandlerRegistry;
use AlexFigures\JsonApi\Filter\Handler\Registry\SortHandlerRegistry;
use AlexFigures\JsonApi\Filter\Operator\BetweenOperator;
use AlexFigures\JsonApi\Filter\Operator\EqualOperator;
use AlexFigures\JsonApi\Filter\Operator\GreaterOrEqualOperator;
use AlexFigures\JsonApi\Filter\Operator\GreaterThanOperator;
use AlexFigures\JsonApi\Filter\Operator\ILikeOperator;
use AlexFigures\JsonApi\Filter\Operator\InOperator;
use AlexFigures\JsonApi\Filter\Operator\IsNullOperator;
use AlexFigures\JsonApi\Filter\Operator\LessOrEqualOperator;
use AlexFigures\JsonApi\Filter\Operator\LessThanOperator;
use AlexFigures\JsonApi\Filter\Operator\LikeOperator;
use AlexFigures\JsonApi\Filter\Operator\NotEqualOperator;
use AlexFigures\JsonApi\Filter\Operator\NotInOperator;
use AlexFigures\JsonApi\Filter\Operator\Registry;
use AlexFigures\JsonApi\Filter\Parser\FilterParser;
use AlexFigures\JsonApi\Http\Cache\CacheKeyBuilder;
use AlexFigures\JsonApi\Http\Cache\ConditionalRequestEvaluator;
use AlexFigures\JsonApi\Http\Cache\EtagGeneratorInterface;
use AlexFigures\JsonApi\Http\Cache\HashEtagGenerator;
use AlexFigures\JsonApi\Http\Cache\HeadersApplier;
use AlexFigures\JsonApi\Http\Cache\LastModifiedResolver;
use AlexFigures\JsonApi\Http\Cache\SurrogateKeyBuilder;
use AlexFigures\JsonApi\Http\Cache\VersionEtagGenerator;
use AlexFigures\JsonApi\Http\Controller\CollectionController;
use AlexFigures\JsonApi\Http\Controller\CreateResourceController;
use AlexFigures\JsonApi\Http\Controller\DeleteResourceController;
use AlexFigures\JsonApi\Http\Controller\OpenApiController;
use AlexFigures\JsonApi\Http\Controller\RelatedController;
use AlexFigures\JsonApi\Http\Controller\RelationshipGetController;
use AlexFigures\JsonApi\Http\Controller\RelationshipWriteController;
use AlexFigures\JsonApi\Http\Controller\ResourceController;
use AlexFigures\JsonApi\Http\Controller\Support\JsonApiResponseFactory;
use AlexFigures\JsonApi\Http\Controller\Support\OperationValidator;
use AlexFigures\JsonApi\Http\Controller\Support\RequestDecoder;
use AlexFigures\JsonApi\Http\Controller\UpdateResourceController;
use AlexFigures\JsonApi\Http\Document\DocumentBuilder;
use AlexFigures\JsonApi\Http\Error\CorrelationIdProvider;
use AlexFigures\JsonApi\Http\Error\ErrorBuilder;
use AlexFigures\JsonApi\Http\Error\ErrorMapper;
use AlexFigures\JsonApi\Http\Error\JsonApiExceptionListener;
use AlexFigures\JsonApi\Http\Link\LinkGenerator;
use AlexFigures\JsonApi\Http\Negotiation\MediaTypePolicyProviderInterface;
use AlexFigures\JsonApi\Http\Relationship\LinkageBuilder;
use AlexFigures\JsonApi\Http\Relationship\WriteRelationshipsResponseConfig;
use AlexFigures\JsonApi\Http\Request\FilteringWhitelist;
use AlexFigures\JsonApi\Http\Request\PaginationConfig;
use AlexFigures\JsonApi\Http\Request\QueryParser;
use AlexFigures\JsonApi\Http\Request\SortingWhitelist;
use AlexFigures\JsonApi\Http\Safety\LimitsEnforcer;
use AlexFigures\JsonApi\Http\Safety\RequestComplexityScorer;
use AlexFigures\JsonApi\Http\Validation\ConstraintViolationMapper;
use AlexFigures\JsonApi\Http\Write\ChangeSetFactory;
use AlexFigures\JsonApi\Http\Write\InputDocumentValidator;
use AlexFigures\JsonApi\Http\Write\RelationshipDocumentValidator;
use AlexFigures\JsonApi\Http\Write\WriteConfig;
use AlexFigures\JsonApi\Invalidation\InvalidationDispatcher;
use AlexFigures\JsonApi\Invalidation\NullPurger;
use AlexFigures\JsonApi\Invalidation\SurrogatePurgerInterface;
use AlexFigures\JsonApi\Profile\Builtin\AuditTrailProfile;
use AlexFigures\JsonApi\Profile\Builtin\RelationshipCountsProfile;
use AlexFigures\JsonApi\Profile\Builtin\SoftDeleteProfile;
use AlexFigures\JsonApi\Profile\Negotiation\ProfileNegotiator;
use AlexFigures\JsonApi\Profile\ProfileRegistry;
use AlexFigures\JsonApi\Resource\Registry\CustomRouteRegistryInterface;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistry;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

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
            '%jsonapi.atomic.enabled%',
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

    $services->set(LastModifiedResolver::class)->args(['%jsonapi.cache%']);

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
            service(ResourceController::class),
            service(RelationshipGetController::class),
            service(\AlexFigures\JsonApi\Contract\Data\WriteConcurrencyGuardInterface::class)->nullOnInvalid(),
        ])
        ->tag('kernel.event_subscriber')
    ;

    $services
        ->set(ProfileRegistry::class)
        ->args([
            tagged_iterator('jsonapi.profile'),
            '%jsonapi.discovered_resources%', '%jsonapi.profiles.enabled_by_default%', '%jsonapi.profiles.per_type%',
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

    // Controller support services
    $services->set(OperationValidator::class)
        ->args([
            service(ErrorMapper::class),
        ])
    ;

    $services->set(RequestDecoder::class)
        ->args([
            service(ErrorMapper::class),
            service(\AlexFigures\JsonApi\Http\Negotiation\MediaTypePolicyProviderInterface::class),
        ])
    ;

    $services->set(JsonApiResponseFactory::class);

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
        ->set(\AlexFigures\JsonApi\Resource\Registry\CustomRouteRegistry::class)
        ->args([
            [], // Will be replaced by ResourceDiscoveryPass
        ])
    ;

    $services
        ->alias(\AlexFigures\JsonApi\Resource\Registry\CustomRouteRegistryInterface::class, \AlexFigures\JsonApi\Resource\Registry\CustomRouteRegistry::class)
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

    $services->set(FilterParser::class)->args(['%jsonapi.filter_max_depth%', service(\AlexFigures\JsonApi\Filter\Operator\Registry::class)]);

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
            service(\AlexFigures\JsonApi\Contract\Data\RepresentationPreloaderInterface::class)->nullOnInvalid(),
        ])
    ;

    // JSON:API Response Factory for custom controllers
    $services
        ->set(\AlexFigures\JsonApi\Http\Response\JsonApiResponseFactory::class)
        ->args([
            service(DocumentBuilder::class),
            service(LinkGenerator::class),
            service(ErrorBuilder::class),
            service(ResourceRegistryInterface::class),
        ])
    ;

    // AtomicConfig for OpenAPI (always available, even when atomic is disabled)
    $services
        ->set('jsonapi.atomic_config_for_openapi', \AlexFigures\JsonApi\Atomic\AtomicConfig::class)
        ->args([
            '%jsonapi.atomic.enabled%',
            '%jsonapi.atomic.endpoint%',
            '%jsonapi.atomic.require_ext_header%',
            '%jsonapi.atomic.max_operations%',
            '%jsonapi.atomic.return_policy%',
            '%jsonapi.atomic.allow_href%',
            '%jsonapi.atomic.lid.accept_in_resource_and_identifier%',
            '%jsonapi.route_prefix%',
        ])
    ;

    $services
        ->set(CustomEndpointCollector::class)
        ->args([
            service('router'),
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
            service('jsonapi.atomic_config_for_openapi'),
            service(CustomEndpointCollector::class),
            service(PaginationConfig::class),
            service('serializer.mapping.class_metadata_factory')->nullOnInvalid(),
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
        ->set(\AlexFigures\JsonApi\Http\Controller\SwaggerUiController::class)
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
            '%jsonapi.relationship_max_identifiers%',
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
        ->set(\AlexFigures\JsonApi\Http\Controller\OptionsController::class)
        ->arg('$headEnabled', '%jsonapi.performance.head_enabled%')
        ->autowire()
        ->autoconfigure()
        ->tag('controller.service_arguments')
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

    $services->set(\AlexFigures\JsonApi\Http\Authorization\RelationshipAccessChecker::class)->args([
        service(\AlexFigures\JsonApi\Http\Authorization\RelationshipAuthorizerInterface::class)->nullOnInvalid(),
    ]);

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

    $services->set(\AlexFigures\JsonApi\Bridge\Symfony\Command\ValidateProfilesCommand::class)
        ->args([service(\AlexFigures\JsonApi\Profile\ProfileRegistry::class), service(ResourceRegistryInterface::class), service('doctrine.orm.entity_manager')->nullOnInvalid(), service('parameter_bag')])->autoconfigure()->tag('console.command');

    $services->set(\AlexFigures\JsonApi\Http\Controller\JsonSchemaController::class)->args([
        service(OpenApiSpecGenerator::class), '%jsonapi.docs.generator.json_schema%', service(\AlexFigures\JsonApi\Profile\ProfileRegistry::class),
    ])->tag('controller.service_arguments');

    // Automatic route loader
    $services
        ->set(\AlexFigures\JsonApi\Bridge\Symfony\Routing\JsonApiRouteLoader::class)
        ->args([
            service(ResourceRegistry::class),
            '%jsonapi.route_prefix%',
            true, // enableRelationshipRoutes
            '%jsonapi.docs.generator.openapi%',
            '%jsonapi.docs.ui%',
            service(\AlexFigures\JsonApi\Resource\Registry\CustomRouteRegistry::class),
            null,
            '%jsonapi.docs.generator.json_schema%',
            '%jsonapi.performance.head_enabled%',
        ])
        ->tag('routing.loader')
    ;

    // NullObject implementations for optional dependencies
    // Registered with low priority so users can override them

    $services
        ->set('jsonapi.null_existence_checker', \AlexFigures\JsonApi\Bridge\Symfony\Null\NullExistenceChecker::class)
    ;

    $services
        ->alias(ExistenceChecker::class, 'jsonapi.null_existence_checker')
    ;

    $services
        ->set('jsonapi.null_relationship_reader', \AlexFigures\JsonApi\Bridge\Symfony\Null\NullRelationshipReader::class)
    ;

    $services
        ->alias(RelationshipReader::class, 'jsonapi.null_relationship_reader')
    ;

    $services
        ->set('jsonapi.null_relationship_updater', \AlexFigures\JsonApi\Bridge\Symfony\Null\NullRelationshipUpdater::class)
    ;

    $services
        ->alias(RelationshipUpdater::class, 'jsonapi.null_relationship_updater')
    ;

    $services
        ->set('jsonapi.null_resource_processor', \AlexFigures\JsonApi\Bridge\Symfony\Null\NullResourceProcessor::class)
    ;

    $services
        ->alias(ResourceProcessor::class, 'jsonapi.null_resource_processor')
    ;

    $services
        ->set('jsonapi.null_resource_repository', \AlexFigures\JsonApi\Bridge\Symfony\Null\NullResourceRepository::class)
    ;

    $services
        ->alias(ResourceRepository::class, 'jsonapi.null_resource_repository')
    ;

    $services
        ->set('jsonapi.null_transaction_manager', \AlexFigures\JsonApi\Contract\Tx\NullTransactionManager::class)
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
    $services->set(ILikeOperator::class)->tag('jsonapi.filter.operator');
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

    $services->set(\AlexFigures\JsonApi\Bridge\Doctrine\Concurrency\DoctrineWriteConcurrencyGuard::class)->args([
        service('doctrine'), service(ResourceRegistryInterface::class),
        service(\AlexFigures\JsonApi\Bridge\Doctrine\Transaction\DoctrineTransactionManager::class),
    ]);
    $services->set(\AlexFigures\JsonApi\Bridge\Doctrine\Identifier\DoctrineIdentifierMetadataValidator::class)->args([
        service('doctrine'),
    ]);

    // Doctrine Bridge Services
    // These are registered here so users don't have to manually configure them
    // They will be used when data_layer.provider is set to 'doctrine' (default)

    // SerializerEntityInstantiator - uses the Symfony Serializer to instantiate entities
    // Mirrors the approach used by API Platform
    $services
        ->set(\AlexFigures\JsonApi\Bridge\Doctrine\Instantiator\SerializerEntityInstantiator::class)
        ->args([
            service('doctrine'),
            service(PropertyAccessorInterface::class),
            service('serializer.mapping.class_metadata_factory')->nullOnInvalid(),
        ])
    ;

    $services
        ->set(\AlexFigures\JsonApi\Resource\Mapper\DefaultReadMapper::class)
        ->autowire()
        ->autoconfigure();

    $services->alias(
        \AlexFigures\JsonApi\Resource\Mapper\ReadMapperInterface::class,
        \AlexFigures\JsonApi\Resource\Mapper\DefaultReadMapper::class
    )->public(false);

    $services
        ->set(\AlexFigures\JsonApi\Bridge\Doctrine\Repository\GenericDoctrineRepository::class)
        ->args([
            service('doctrine'),
            service(ResourceRegistryInterface::class),
            service(DoctrineFilterCompiler::class),
            service(FilterHandlerRegistry::class),
            service(SortHandlerRegistry::class),
            service(\AlexFigures\JsonApi\Resource\Mapper\ReadMapperInterface::class),
            '%jsonapi.performance.doctrine.collection_sort_policy%',
            service(\Symfony\Component\HttpFoundation\RequestStack::class),
        ])
    ;

    $services->set(\AlexFigures\JsonApi\Http\Document\Fetch\RepresentationFetchPlanner::class)->args(['%jsonapi.relationships.linkage_in_resource%']);
    $services->set(\AlexFigures\JsonApi\Bridge\Doctrine\Read\DoctrineRepresentationPreloader::class)->args([
        service('doctrine'), service(ResourceRegistryInterface::class), service(PropertyAccessorInterface::class),
        service(\AlexFigures\JsonApi\Resource\Mapper\ReadMapperInterface::class),
        service(\AlexFigures\JsonApi\Http\Document\Fetch\RepresentationFetchPlanner::class), service(ErrorMapper::class), '%jsonapi.limits%', service(ResourceRepository::class), service(QueryParser::class), tagged_iterator('jsonapi.relationship_batch_reader'), '%jsonapi.relationships.unplanned_read_policy%',
    ]);

    $services->set(\AlexFigures\JsonApi\Bridge\Symfony\Locator\ResourceProcessorLocator::class)->args([
        tagged_iterator('jsonapi.persister'),
        service(\AlexFigures\JsonApi\Bridge\Doctrine\Persister\ValidatingDoctrineProcessor::class),
    ]);

    $services->set(\AlexFigures\JsonApi\Bridge\Symfony\Locator\RelationshipReaderLocator::class)->args([
        tagged_iterator('jsonapi.relationship_reader'), service('jsonapi.null_relationship_reader'),
    ]);
    $services->set(\AlexFigures\JsonApi\Bridge\Symfony\Locator\RelationshipUpdaterLocator::class)->args([
        tagged_iterator('jsonapi.relationship_updater'), service('jsonapi.null_relationship_updater'),
    ]);

    // ResourceRepositoryLocator - dispatches to custom TypedResourceRepository or falls back to GenericDoctrineRepository
    $services
        ->set(\AlexFigures\JsonApi\Bridge\Symfony\Locator\ResourceRepositoryLocator::class)
        ->args([
            tagged_iterator('jsonapi.resource_repository'),  // Custom typed repositories
            service(\AlexFigures\JsonApi\Bridge\Doctrine\Repository\GenericDoctrineRepository::class),  // Fallback for Doctrine entities
        ])
    ;

    $services
        ->set(\AlexFigures\JsonApi\Http\Validation\DatabaseErrorMapper::class)
        ->args([
            service(ResourceRegistryInterface::class),
            service(\AlexFigures\JsonApi\Http\Error\ErrorMapper::class),
        ])
    ;

    $services
        ->set(\AlexFigures\JsonApi\Resource\Relationship\RelationshipResolver::class)
        ->args([
            service('doctrine'),
            service(ResourceRegistryInterface::class),
            service(PropertyAccessorInterface::class),
            service(ErrorMapper::class),
            false, service('request_stack'),
        ])
    ;

    $services->set(\AlexFigures\JsonApi\Bridge\Doctrine\Profile\ProfileWriteHooks::class)->args([
        service('request_stack'), service(PropertyAccessorInterface::class),
        service(\AlexFigures\JsonApi\Resource\Relationship\RelationshipResolver::class),
    ]);
    // FlushManager - centralized flush control
    $services
        ->set(FlushManager::class)
        ->args([
            service('doctrine'),
            service(\AlexFigures\JsonApi\Http\Validation\DatabaseErrorMapper::class),
            service(ResourceRegistryInterface::class),
        ])
    ;

    // WriteListener - automatically flushes after write operations
    $services
        ->set(WriteListener::class)
        ->args([
            service(FlushManager::class),
            service(\AlexFigures\JsonApi\Http\Validation\DatabaseErrorMapper::class),
        ])
        ->tag('kernel.event_subscriber')
    ;

    $services->set(\AlexFigures\JsonApi\Resource\Mapper\DefaultWriteMapper::class)->args([service(PropertyAccessorInterface::class)]);
    $services->alias(\AlexFigures\JsonApi\Resource\Mapper\WriteMapperInterface::class, \AlexFigures\JsonApi\Resource\Mapper\DefaultWriteMapper::class);
    $services->set(\AlexFigures\JsonApi\Bridge\Doctrine\Persister\DoctrineWriteRequestMapper::class)->args([
        service(\AlexFigures\JsonApi\Bridge\Doctrine\Instantiator\SerializerEntityInstantiator::class),
        service('validator'), service(ConstraintViolationMapper::class),
        service(\AlexFigures\JsonApi\Resource\Mapper\WriteMapperInterface::class), service('request_stack'),
    ]);

    $services
        ->set(\AlexFigures\JsonApi\Bridge\Doctrine\Persister\ValidatingDoctrineProcessor::class)
        ->args([
            service('doctrine'),
            service(ResourceRegistryInterface::class),
            service(PropertyAccessorInterface::class),
            service('validator'),
            service(ConstraintViolationMapper::class),
            service(\AlexFigures\JsonApi\Bridge\Doctrine\Instantiator\SerializerEntityInstantiator::class),
            service(\AlexFigures\JsonApi\Resource\Relationship\RelationshipResolver::class),
            service(FlushManager::class),
            service(\AlexFigures\JsonApi\Bridge\Doctrine\Profile\ProfileWriteHooks::class),
            service(\AlexFigures\JsonApi\Bridge\Doctrine\Persister\DoctrineWriteRequestMapper::class),
        ])
    ;

    $services
        ->set(\AlexFigures\JsonApi\Bridge\Serializer\Normalizer\JsonApiRelationshipDenormalizer::class)
        ->args([
            service(\AlexFigures\JsonApi\Resource\Relationship\RelationshipResolver::class),
            service(ResourceRegistryInterface::class),
        ])
        ->tag('serializer.normalizer', ['priority' => 100]) // High priority to handle relationships first
    ;

    $services
        ->set(\AlexFigures\JsonApi\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler::class)
        ->args([
            service('doctrine'),
            service(ResourceRegistryInterface::class),
            service(PropertyAccessorInterface::class),
            service(FlushManager::class),
            service(ResourceRepository::class),
            service(QueryParser::class), service(\Symfony\Component\HttpFoundation\RequestStack::class)->nullOnInvalid(), '%jsonapi.relationships.unplanned_read_policy%',
        ])
    ;

    $services
        ->set(\AlexFigures\JsonApi\Bridge\Doctrine\Transaction\DoctrineTransactionManager::class)
        ->args([
            service('doctrine'),
            service(FlushManager::class),
            tagged_iterator('jsonapi.persister'),
        ])
    ;

    $services
        ->set(\AlexFigures\JsonApi\Bridge\Doctrine\ExistenceChecker\DoctrineExistenceChecker::class)
        ->args([
            service('doctrine'),
            service(ResourceRegistryInterface::class),
        ])
    ;
};
