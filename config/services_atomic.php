<?php

declare(strict_types=1);

use AlexFigures\JsonApi\Atomic\AtomicConfig;
use AlexFigures\JsonApi\Atomic\Execution\AtomicTransaction;
use AlexFigures\JsonApi\Atomic\Execution\Handlers\AddHandler;
use AlexFigures\JsonApi\Atomic\Execution\Handlers\RelationshipOps;
use AlexFigures\JsonApi\Atomic\Execution\Handlers\RemoveHandler;
use AlexFigures\JsonApi\Atomic\Execution\Handlers\UpdateHandler;
use AlexFigures\JsonApi\Atomic\Execution\OperationDispatcher;
use AlexFigures\JsonApi\Atomic\Parser\AtomicRequestParser;
use AlexFigures\JsonApi\Atomic\Result\ResultBuilder;
use AlexFigures\JsonApi\Atomic\Validation\AtomicValidator;
use AlexFigures\JsonApi\Bridge\Doctrine\Flush\FlushManager;
use AlexFigures\JsonApi\Bridge\Symfony\Controller\AtomicController;
use AlexFigures\JsonApi\Contract\Data\RelationshipUpdater;
use AlexFigures\JsonApi\Contract\Data\ResourceProcessor;
use AlexFigures\JsonApi\Contract\Tx\TransactionManager;
use AlexFigures\JsonApi\Http\Document\DocumentBuilder;
use AlexFigures\JsonApi\Http\Error\ErrorMapper;
use AlexFigures\JsonApi\Http\Negotiation\MediaTypeNegotiator;
use AlexFigures\JsonApi\Http\Negotiation\MediaTypePolicyProviderInterface;
use AlexFigures\JsonApi\Http\Write\ChangeSetFactory;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Services for Atomic Operations.
 *
 * Loaded only if atomic.enabled = true.
 */
return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services
        ->set(AtomicConfig::class)
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
        ->set(MediaTypeNegotiator::class)
        ->args([
            service(AtomicConfig::class),
            service(MediaTypePolicyProviderInterface::class),
        ])
    ;

    $services
        ->set(AtomicRequestParser::class)
        ->args([
            service(AtomicConfig::class),
            service(ErrorMapper::class),
        ])
    ;

    $services
        ->set(AtomicValidator::class)
        ->args([
            service(AtomicConfig::class),
            service(ResourceRegistryInterface::class),
            service(ErrorMapper::class),
        ])
    ;

    $services
        ->set(AtomicTransaction::class)
        ->args([
            service(TransactionManager::class),
            service(ResourceRegistryInterface::class),
            service(\AlexFigures\JsonApi\Http\Write\InputDocumentValidator::class),
        ])
    ;

    $services
        ->set(AddHandler::class)
        ->args([
            service(ResourceProcessor::class),
            service(ChangeSetFactory::class),
            service(ResourceRegistryInterface::class),
            service(PropertyAccessorInterface::class),
            service(FlushManager::class),
            service(\AlexFigures\JsonApi\Http\Write\WriteConfig::class),
            service(\AlexFigures\JsonApi\Http\Write\InputDocumentValidator::class),
        ])
    ;

    $services
        ->set(UpdateHandler::class)
        ->args([
            service(ResourceProcessor::class),
            service(ChangeSetFactory::class),
            service(ResourceRegistryInterface::class),
            service(PropertyAccessorInterface::class),
            service(ErrorMapper::class),
            service(\AlexFigures\JsonApi\Http\Write\InputDocumentValidator::class),
        ])
    ;

    $services
        ->set(RemoveHandler::class)
        ->args([
            service(ResourceProcessor::class),
            service(ErrorMapper::class),
        ])
    ;

    $services
        ->set(RelationshipOps::class)
        ->args([
            service(RelationshipUpdater::class),
            service(ResourceRegistryInterface::class),
            service(ErrorMapper::class),
        ])
    ;

    $services
        ->set(ResultBuilder::class)
        ->args([
            service(AtomicConfig::class),
            service(DocumentBuilder::class),
        ])
    ;

    $services
        ->set(OperationDispatcher::class)
        ->args([
            service(AtomicTransaction::class),
            service(AddHandler::class),
            service(UpdateHandler::class),
            service(RemoveHandler::class),
            service(RelationshipOps::class),
            service(ResultBuilder::class),
            service(FlushManager::class),
        ])
    ;

    $services
        ->set(AtomicController::class)
        ->autowire()
        ->autoconfigure()
        ->tag('controller.service_arguments')
    ;
};
