<?php

declare(strict_types=1);

use AlexFigures\JsonApi\Contract\Data\ResourceRepository;
use AlexFigures\JsonApi\Contract\Tx\TransactionManager;
use AlexFigures\JsonApi\CustomRoute\Context\CustomRouteContextFactory;
use AlexFigures\JsonApi\CustomRoute\Controller\CustomRouteController;
use AlexFigures\JsonApi\CustomRoute\Handler\CustomRouteHandlerRegistry;
use AlexFigures\JsonApi\CustomRoute\Response\CustomRouteResponseBuilder;
use AlexFigures\JsonApi\Http\Document\DocumentBuilder;
use AlexFigures\JsonApi\Http\Error\ErrorBuilder;
use AlexFigures\JsonApi\Http\Link\LinkGenerator;
use AlexFigures\JsonApi\Http\Request\QueryParser;
use AlexFigures\JsonApi\Resource\Registry\CustomRouteRegistryInterface;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

/**
 * Services for Custom Route Handlers (new in 0.3.0).
 *
 * This provides the infrastructure for handler-based custom routes with:
 * - Automatic JSON:API response formatting
 * - Automatic transaction management
 * - Automatic error handling
 * - Pre-loaded resources
 * - Type-safe results
 */
return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    // CustomRouteContextFactory - Creates context from request
    $services
        ->set(CustomRouteContextFactory::class)
        ->args([
            service(CustomRouteRegistryInterface::class),
            service(ResourceRegistryInterface::class),
            service(ResourceRepository::class),
            service(QueryParser::class),
            service(\AlexFigures\JsonApi\Http\Error\ErrorMapper::class),
        ])
    ;

    // CustomRouteResponseBuilder - Builds JSON:API responses from results
    $services
        ->set(CustomRouteResponseBuilder::class)
        ->args([
            service(DocumentBuilder::class),
            service(LinkGenerator::class),
            service(ErrorBuilder::class),
        ])
    ;

    // CustomRouteHandlerRegistry - Maps route names to handlers
    $services
        ->set(CustomRouteHandlerRegistry::class)
        ->args([
            service(CustomRouteRegistryInterface::class),
            tagged_locator('jsonapi.custom_route_handler'), // Service locator for handlers
        ])
    ;

    // CustomRouteController - Generic controller for all handler-based routes
    $services
        ->set(CustomRouteController::class)
        ->args([
            service(CustomRouteHandlerRegistry::class),
            service(CustomRouteContextFactory::class),
            service(CustomRouteResponseBuilder::class),
            service(TransactionManager::class),
            service(EventDispatcherInterface::class),
            service(ErrorBuilder::class),
            service(LoggerInterface::class)->nullOnInvalid(),
        ])
        ->tag('controller.service_arguments')
    ;
};

