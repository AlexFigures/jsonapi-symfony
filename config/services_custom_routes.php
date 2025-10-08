<?php

declare(strict_types=1);

use JsonApi\Symfony\Contract\Data\ResourceRepository;
use JsonApi\Symfony\Contract\Tx\TransactionManager;
use JsonApi\Symfony\CustomRoute\Context\CustomRouteContextFactory;
use JsonApi\Symfony\CustomRoute\Controller\CustomRouteController;
use JsonApi\Symfony\CustomRoute\Handler\CustomRouteHandlerRegistry;
use JsonApi\Symfony\CustomRoute\Response\CustomRouteResponseBuilder;
use JsonApi\Symfony\Http\Document\DocumentBuilder;
use JsonApi\Symfony\Http\Error\ErrorBuilder;
use JsonApi\Symfony\Http\Link\LinkGenerator;
use JsonApi\Symfony\Http\Request\QueryParser;
use JsonApi\Symfony\Resource\Registry\CustomRouteRegistryInterface;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
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
            service(\JsonApi\Symfony\Http\Error\ErrorMapper::class),
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

