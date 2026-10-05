<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional\Regression;

use AlexFigures\Symfony\Bridge\Symfony\Bundle\JsonApiBundle;
use AlexFigures\Symfony\Tests\Unit\Profile\Fixtures\InjectedProfile;
use AlexFigures\Symfony\Tests\Unit\Profile\Fixtures\InjectedProfileContext;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class RcKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new JsonApiBundle();
    }

    public function getCacheDir(): string
    {
        return '/tmp/jsonapi-rc-kernel-' . getmypid() . '/' . $this->environment;
    }
    public function getLogDir(): string
    {
        return $this->getCacheDir();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test-only', 'test' => true, 'router' => ['utf8' => true], 'serializer' => ['enabled' => true], 'validation' => ['enable_attributes' => true]]);
        $container->extension('jsonapi', [
            'resource_paths' => [],
            'data_layer' => ['provider' => 'custom'],
            'profiles' => ['enabled_by_default' => ['urn:test:injected']],
            'docs' => ['generator' => ['openapi' => ['enabled' => true]], 'ui' => ['enabled' => true]],
            'media_types' => ['channels' => [
                'by_route' => ['scope' => ['route_name' => '^test_route_channel$'], 'response' => ['default' => 'text/plain', 'negotiable' => ['text/plain']]],
                'by_attribute' => ['scope' => ['attribute' => '^html$'], 'response' => ['default' => 'text/html', 'negotiable' => ['text/html']]],
            ]],
        ]);
        $services = $container->services();
        $services->set(InjectedProfileContext::class)->args(['urn:test:injected']);
        $services->set(InjectedProfile::class)->args([service(InjectedProfileContext::class)])->tag('jsonapi.profile');
        $services->set(RcMediaController::class)->public()->tag('controller.service_arguments');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'jsonapi');
        $routes->add('test_route_channel', '/route-channel')->controller([RcMediaController::class, 'plain'])->methods(['GET']);
        $routes->add('test_attribute_channel', '/attribute-channel')->controller([RcMediaController::class, 'html'])->methods(['GET']);
    }
}
