<?php

declare(strict_types=1);

use AlexFigures\Symfony\Atomic\AtomicConfig;
use AlexFigures\Symfony\Atomic\Execution\AtomicTransaction;
use AlexFigures\Symfony\Atomic\Execution\Handlers\AddHandler;
use AlexFigures\Symfony\Atomic\Execution\Handlers\RelationshipOps;
use AlexFigures\Symfony\Atomic\Execution\Handlers\RemoveHandler;
use AlexFigures\Symfony\Atomic\Execution\Handlers\UpdateHandler;
use AlexFigures\Symfony\Atomic\Execution\OperationDispatcher;
use AlexFigures\Symfony\Atomic\Parser\AtomicRequestParser;
use AlexFigures\Symfony\Atomic\Result\ResultBuilder;
use AlexFigures\Symfony\Atomic\Validation\AtomicValidator;
use AlexFigures\Symfony\Bridge\Symfony\Controller\AtomicController;
use AlexFigures\Symfony\Contract\Data\RelationshipUpdater;
use AlexFigures\Symfony\Contract\Data\ResourcePersister;
use AlexFigures\Symfony\Contract\Tx\TransactionManager;
use AlexFigures\Symfony\Http\Document\DocumentBuilder;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Negotiation\MediaTypeNegotiator;
use AlexFigures\Symfony\Http\Write\ChangeSetFactory;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
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
        ])
    ;

    $services
        ->set(AddHandler::class)
        ->args([
            service(ResourcePersister::class),
            service(ChangeSetFactory::class),
            service(ResourceRegistryInterface::class),
            service(PropertyAccessorInterface::class),
        ])
    ;

    $services
        ->set(UpdateHandler::class)
        ->args([
            service(ResourcePersister::class),
            service(ChangeSetFactory::class),
            service(ResourceRegistryInterface::class),
            service(PropertyAccessorInterface::class),
            service(ErrorMapper::class),
        ])
    ;

    $services
        ->set(RemoveHandler::class)
        ->args([
            service(ResourcePersister::class),
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
        ])
    ;

    $services
        ->set(AtomicController::class)
        ->autowire()
        ->autoconfigure()
        ->tag('controller.service_arguments')
    ;
};

