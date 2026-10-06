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
        return '/tmp/jsonapi-rc8f-kernel-' . getmypid() . '/' . $this->environment;
    }
    public function getLogDir(): string
    {
        return $this->getCacheDir();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test-only', 'test' => true, 'router' => ['utf8' => true], 'serializer' => ['enabled' => true], 'validation' => ['enable_attributes' => true]]);
        $container->extension('jsonapi', [
            'atomic' => ['enabled' => $this->environment === 'doctrine_typed'],
            'resource_paths' => [__DIR__ . '/Fixtures'],
            'cache' => ['etag' => ['strategy' => 'version'], 'last_modified' => ['collections_max_of' => false], 'conditional' => ['require_if_match_on_write' => false]],
            'data_layer' => ['provider' => $this->environment === 'doctrine_typed' ? 'doctrine' : 'custom', 'repository' => \AlexFigures\Symfony\Tests\Functional\Regression\Fixtures\RcTypedRepository::class],
            'profiles' => ['enabled_by_default' => ['urn:test:injected'], 'per_type' => ['rc-memory' => ['urn:jsonapi:profile:audit-trail']], 'audit_trail' => ['expose_in_meta' => false, 'created_by' => 'createdBy', 'updated_by' => 'updatedBy']],
            'docs' => ['generator' => ['openapi' => ['enabled' => true]], 'ui' => ['enabled' => true]],
            'media_types' => ['channels' => [
                'by_route' => ['scope' => ['route_name' => '^test_route_channel$'], 'response' => ['default' => 'text/plain', 'negotiable' => ['text/plain']]],
                'by_attribute' => ['scope' => ['attribute' => '^html$'], 'response' => ['default' => 'text/html', 'negotiable' => ['text/html']]],
            ]],
        ]);
        $services = $container->services();
        if ($this->environment === 'doctrine_typed') {
            $services->set('doctrine', \AlexFigures\Symfony\Tests\Fixtures\Doctrine\TestManagerRegistry::class)->args([[]]);
        }
        $services->set(\AlexFigures\Symfony\Tests\Functional\Regression\Fixtures\RcAuditIdentity::class);
        $services->set(\AlexFigures\Symfony\Profile\Builtin\AuditTrailProfile::class)->args([['userProvider' => service(\AlexFigures\Symfony\Tests\Functional\Regression\Fixtures\RcAuditIdentity::class)]])->tag('jsonapi.profile');
        $services->set(InjectedProfileContext::class)->args(['urn:test:injected']);
        $services->set(InjectedProfile::class)->args([service(InjectedProfileContext::class)])->tag('jsonapi.profile');
        $services->set(\AlexFigures\Symfony\Tests\Functional\Regression\Fixtures\RcTypedRepository::class)->tag('jsonapi.resource_repository');
        $services->set(\AlexFigures\Symfony\Tests\Functional\Regression\Fixtures\RcTypedPersister::class)->autoconfigure();
        $services->set(RcMediaController::class)->public()->tag('controller.service_arguments');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'jsonapi');
        if ($this->environment === 'doctrine_typed') {
            $routes->add('test_atomic', '/api/operations')->controller(\AlexFigures\Symfony\Bridge\Symfony\Controller\AtomicController::class)->methods(['POST']);
        }
        $routes->add('test_version', '/version/{version}')->controller([RcMediaController::class, 'version'])->methods(['GET']);
        $routes->add('test_route_channel', '/route-channel')->controller([RcMediaController::class, 'plain'])->methods(['GET']);
        $routes->add('test_attribute_channel', '/attribute-channel')->controller([RcMediaController::class, 'html'])->methods(['GET']);
    }
}
