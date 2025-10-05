<?php

declare(strict_types=1);

use JsonApi\Symfony\Bridge\Symfony\EventSubscriber\ContentNegotiationSubscriber;
use JsonApi\Symfony\Http\Exception\JsonApiHttpException;
use JsonApi\Symfony\Resource\Registry\ResourceRegistry;
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

    $services->set(JsonApiHttpException::class)->abstract();
    $services->set(ResourceRegistry::class);
};
