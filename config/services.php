<?php

declare(strict_types=1);

use JsonApi\Symfony\Bridge\Symfony\EventSubscriber\ContentNegotiationSubscriber;
use JsonApi\Symfony\Http\Controller\CollectionController;
use JsonApi\Symfony\Http\Controller\CreateResourceController;
use JsonApi\Symfony\Http\Controller\DeleteResourceController;
use JsonApi\Symfony\Http\Controller\RelatedController;
use JsonApi\Symfony\Http\Controller\RelationshipGetController;
use JsonApi\Symfony\Http\Controller\RelationshipWriteController;
use JsonApi\Symfony\Http\Controller\ResourceController;
use JsonApi\Symfony\Http\Controller\UpdateResourceController;
use JsonApi\Symfony\Http\Document\DocumentBuilder;
use JsonApi\Symfony\Http\Error\CorrelationIdProvider;
use JsonApi\Symfony\Http\Error\ErrorBuilder;
use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Error\JsonApiExceptionListener;
use JsonApi\Symfony\Http\Link\LinkGenerator;
use JsonApi\Symfony\Http\Request\PaginationConfig;
use JsonApi\Symfony\Http\Request\QueryParser;
use JsonApi\Symfony\Http\Request\SortingWhitelist;
use JsonApi\Symfony\Http\Validation\ConstraintViolationMapper;
use JsonApi\Symfony\Http\Relationship\LinkageBuilder;
use JsonApi\Symfony\Http\Relationship\WriteRelationshipsResponseConfig;
use JsonApi\Symfony\Http\Write\ChangeSetFactory;
use JsonApi\Symfony\Http\Write\InputDocumentValidator;
use JsonApi\Symfony\Http\Write\RelationshipDocumentValidator;
use JsonApi\Symfony\Http\Write\WriteConfig;
use JsonApi\Symfony\Resource\Registry\ResourceRegistry;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use JsonApi\Symfony\Contract\Data\ExistenceChecker;
use JsonApi\Symfony\Contract\Data\RelationshipReader;
use JsonApi\Symfony\Contract\Data\RelationshipUpdater;
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
            '%jsonapi.sorting.whitelist%',
        ])
    ;

    $services
        ->set(QueryParser::class)
        ->args([
            service(ResourceRegistryInterface::class),
            service(PaginationConfig::class),
            service(SortingWhitelist::class),
            service(ErrorMapper::class),
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
        ])
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
};
