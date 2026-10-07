<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional\Regression;

use AlexFigures\Symfony\Profile\ProfileRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request;

final class RcContainerContractTest extends TestCase
{
    #[DataProvider('mediaPolicies')]
    public function testConfiguredDefaultMediaAppliesToGeneratedReadsAndWrites(string $environment): void
    {
        $kernel = new RcKernel($environment, false);
        try {
            $response = $kernel->handle(Request::create('/api/rc-memory/stored', server: ['CONTENT_TYPE' => 'application/json']), catch: false);
            self::assertSame(200, $response->getStatusCode());
            self::assertStringStartsWith('application/json', $response->headers->get('Content-Type'));
            foreach (['POST', 'PATCH'] as $method) {
                $data = ['type' => 'rc-memory', 'attributes' => ['title' => 'Command']];
                if ($method === 'PATCH') {
                    $data['id'] = 'typed';
                }
                $path = $method === 'POST' ? '/api/rc-memory' : '/api/rc-memory/typed';
                $response = $kernel->handle(Request::create($path, $method, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: json_encode(['data' => $data], \JSON_THROW_ON_ERROR)), catch: false);
                self::assertSame($method === 'POST' ? 201 : 200, $response->getStatusCode());
                self::assertStringStartsWith('application/json', $response->headers->get('Content-Type'));
            }
            self::assertSame(415, $kernel->handle(Request::create('/api/rc-memory', 'POST', server: ['CONTENT_TYPE' => 'application/vnd.api+json'], content: '{}'))->getStatusCode());
            self::assertSame(406, $kernel->handle(Request::create('/api/rc-memory', server: ['HTTP_ACCEPT' => 'text/plain']))->getStatusCode());
            if ($environment === 'media_default') {
                $response = $kernel->handle(Request::create('/api/rc-memory/stored', server: ['HTTP_ACCEPT' => 'application/vnd.api+json']), catch: false);
                self::assertStringStartsWith('application/vnd.api+json', $response->headers->get('Content-Type'));
            }
        } finally {
            $kernel->shutdown();
        }
    }

    public static function mediaPolicies(): iterable
    {
        yield ['media_default'];
        yield ['media_legacy'];
    }

    public function testHeadDisabledRejectsAutomaticGetMatchingAndOptionsAgrees(): void
    {
        $kernel = new RcKernel('head_disabled', false);
        try {
            foreach (['/api/rc-memory', '/api/rc-memory/stored', '/api/rc-tagged/stored/one', '/api/rc-tagged/stored/relationships/many'] as $path) {
                self::assertSame(200, $kernel->handle(Request::create($path))->getStatusCode());
                $head = $kernel->handle(Request::create($path, 'HEAD'));
                self::assertSame(405, $head->getStatusCode());
                $response = $kernel->handle(Request::create($path, 'OPTIONS'));
                self::assertSame($response->headers->get('Allow'), $head->headers->get('Allow'));
                self::assertStringNotContainsString('HEAD', $response->headers->get('Allow'));
                self::assertStringContainsString('GET', $response->headers->get('Allow'));
            }
        } finally {
            $kernel->shutdown();
        }
    }

    public function testTaggedResourceOutsidePathsHasRoutesAndRequiredReadIdentity(): void
    {
        $kernel = new RcKernel('tagged', false);
        try {
            $response = $kernel->handle(Request::create('/api/rc-tagged/stored'), catch: false);
            self::assertSame(200, $response->getStatusCode());
            $document = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            self::assertSame('stored', $document['data']['id']);
            $response = $kernel->handle(Request::create('/_jsonapi/openapi.json'), catch: false);
            $spec = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            $matched = 0;
            foreach ($spec['components']['schemas'] as $schema) {
                if (($schema['properties']['type']['const'] ?? null) === 'rc-tagged') {
                    ++$matched;
                    self::assertContains('id', $schema['required']);
                    self::assertFalse($schema['properties']['id']['nullable'] ?? false);
                }
            }
            self::assertSame(2, $matched);
        } finally {
            $kernel->shutdown();
        }
    }

    public function testConfiguredAuditProfileResolvesRenamedAttributeFieldsThroughRegistry(): void
    {
        $kernel = new RcKernel('audit_attributes', false);
        try {
            $kernel->boot();
            $container = $kernel->getContainer()->get('test.service_container');
            $profile = $container->get(\AlexFigures\Symfony\Profile\Builtin\AuditTrailProfile::class);
            $context = new \AlexFigures\Symfony\Profile\ProfileContext([$profile->uri() => $profile]);
            $changes = new \AlexFigures\Symfony\Contract\Data\ChangeSet();
            foreach ($context->writeHooks() as $hook) {
                $hook->onBeforeCreate($context, 'rc-tagged', $changes);
            }
            self::assertSame('constructor-di@example.test', $changes->attributes['insertedBy']);
            self::assertInstanceOf(\DateTimeImmutable::class, $changes->attributes['insertedAt']);
            self::assertArrayNotHasKey('createdBy', $changes->attributes);
            $changes = new \AlexFigures\Symfony\Contract\Data\ChangeSet();
            foreach ($context->writeHooks() as $hook) {
                $hook->onBeforeUpdate($context, 'rc-tagged', 'stored', $changes);
            }
            self::assertSame('constructor-di@example.test', $changes->attributes['changedBy']);
            self::assertInstanceOf(\DateTimeImmutable::class, $changes->attributes['changedAt']);
        } finally {
            $kernel->shutdown();
        }
    }

    public function testAutoconfiguredTypedRelationshipsHandleGeneratedEndpoints(): void
    {
        $kernel = new RcKernel('typed_relationships', false);
        try {
            foreach (['one', 'many'] as $rel) {
                $response = $kernel->handle(Request::create('/api/rc-tagged/stored/relationships/' . $rel), catch: false);
                self::assertSame(200, $response->getStatusCode());
                $doc = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
                self::assertSame('stored', ($rel === 'one' ? $doc['data'] : $doc['data'][0])['id']);
                $response = $kernel->handle(Request::create('/api/rc-tagged/stored/' . $rel), catch: false);
                self::assertSame(200, $response->getStatusCode());
                $doc = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
                self::assertSame('Typed relation', ($rel === 'one' ? $doc['data'] : $doc['data'][0])['attributes']['title']);
            }
            foreach (['PATCH' => 'one', 'POST' => 'many', 'DELETE' => 'many'] as $method => $rel) {
                $target = ['type' => 'rc-memory', 'id' => 'stored'];
                $response = $kernel->handle(Request::create('/api/rc-tagged/stored/relationships/' . $rel, $method, server: ['CONTENT_TYPE' => 'application/vnd.api+json'], content: json_encode(['data' => $rel === 'one' ? $target : [$target]], \JSON_THROW_ON_ERROR)), catch: false);
                self::assertSame(200, $response->getStatusCode());
            }
            $handler = $kernel->getContainer()->get('test.service_container')->get(RcTypedRelationshipHandler::class);
            self::assertSame(['replace-one', 'add-many', 'remove-many'], $handler->writes);
        } finally {
            $kernel->shutdown();
        }
    }

    public function testResourcePrefixControlsHttpRoutesAndRepresentationLinks(): void
    {
        $kernel = new RcKernel('route_prefix', false);
        try {
            foreach (['/reference/rc-routed/stored', '/reference/rc-routed'] as $url) {
                $response = $kernel->handle(Request::create($url, server: ['HTTP_ACCEPT' => 'application/vnd.api+json']), catch: false);
                self::assertSame(200, $response->getStatusCode());
                $doc = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
                $resource = isset($doc['data'][0]) ? $doc['data'][0] : $doc['data'];
                self::assertSame('Routed', $resource['attributes']['title']);
                self::assertSame('/reference/rc-routed/stored', parse_url($resource['links']['self'], \PHP_URL_PATH));
                if (isset($doc['links']['first'])) {
                    self::assertSame('/reference/rc-routed', parse_url($doc['links']['first'], \PHP_URL_PATH));
                }
            }
            self::assertSame(404, $kernel->handle(Request::create('/api/rc-routed/stored'))->getStatusCode());
            self::assertSame(200, $kernel->handle(Request::create('/api/rc-memory/stored', server: ['HTTP_ACCEPT' => 'application/vnd.api+json']), catch: false)->getStatusCode());
        } finally {
            $kernel->shutdown();
        }
    }

    public function testPerTypeAuditDefaultsKeepConstructorDiAndBundleConfiguration(): void
    {
        $kernel = new RcKernel('rc8', false);
        try {
            $kernel->boot();
            $container = $kernel->getContainer()->get('test.service_container');
            $negotiator = $container->get(\AlexFigures\Symfony\Profile\Negotiation\ProfileNegotiator::class);
            $request = Request::create('/api/rc-memory', 'POST');
            $request->attributes->set('type', 'rc-memory');
            $context = $negotiator->negotiate($request)->forType('rc-memory');
            $changes = new \AlexFigures\Symfony\Contract\Data\ChangeSet();
            foreach ($context->writeHooks() as $hook) {
                $hook->onBeforeCreate($context, 'rc-memory', $changes);
            }
            self::assertSame('constructor-di@example.test', $changes->attributes['createdBy']);
            self::assertInstanceOf(\DateTimeImmutable::class, $changes->attributes['createdAt']);
            $response = $kernel->handle(Request::create('/api/rc-memory/stored', server: ['HTTP_ACCEPT' => 'application/vnd.api+json']), catch: false);
            $document = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            self::assertArrayNotHasKey('meta', $document['data'], 'expose_in_meta=false must survive a profile service overriding its constructor config.');
            self::assertStringContainsString('urn:jsonapi:profile:audit-trail', $response->headers->get('Content-Type'));
        } finally {
            $kernel->shutdown();
        }
    }

    public function testVersionStrategyUsesHeaderAndDoesNotFallBackToHash(): void
    {
        $kernel = new RcKernel('rc8', false);
        try {
            foreach (['7' => '"7"', 'absent' => null] as $version => $etag) {
                $response = $kernel->handle(Request::create('/version/' . $version, server: ['HTTP_ACCEPT' => 'application/vnd.api+json']), catch: false);
                self::assertSame(200, $response->getStatusCode());
                self::assertSame($etag, $response->headers->get('ETag'));
            }
        } finally {
            $kernel->shutdown();
        }
    }

    public function testCollectionLastModifiedDisabledInCompiledContainer(): void
    {
        $kernel = new RcKernel('rc8', false);
        try {
            $item = $kernel->handle(Request::create('/api/rc-memory/stored', server: ['HTTP_ACCEPT' => 'application/vnd.api+json']), catch: false);
            self::assertSame(200, $item->getStatusCode());
            self::assertNotNull($item->headers->get('Last-Modified'));
            $collection = $kernel->handle(Request::create('/api/rc-memory', server: ['HTTP_ACCEPT' => 'application/vnd.api+json']), catch: false);
            self::assertSame(200, $collection->getStatusCode());
            self::assertNull($collection->headers->get('Last-Modified'));
        } finally {
            $kernel->shutdown();
        }
    }

    #[DataProvider('typedProviderModes')]
    public function testGeneratedWritesUseAutoconfiguredLegacyTypedPersister(string $environment): void
    {
        $kernel = new RcKernel($environment, false);
        try {
            foreach (['POST' => 'Typed: Command', 'PATCH' => 'Updated: Command'] as $method => $title) {
                $data = ['type' => 'rc-memory', 'attributes' => ['title' => 'Command']];
                if ($method === 'PATCH') {
                    $data['id'] = 'typed';
                }
                $path = $method === 'POST' ? '/api/rc-memory' : '/api/rc-memory/typed';
                $response = $kernel->handle(Request::create($path, $method, server: ['CONTENT_TYPE' => 'application/vnd.api+json', 'HTTP_ACCEPT' => 'application/vnd.api+json'], content: json_encode(['data' => $data], \JSON_THROW_ON_ERROR)), catch: false);
                self::assertSame($method === 'POST' ? 201 : 200, $response->getStatusCode(), $response->getContent());
                $document = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
                self::assertSame($title, $document['data']['attributes']['title']);
            }
            $response = $kernel->handle(Request::create('/api/rc-memory/typed', 'DELETE', server: ['CONTENT_TYPE' => 'application/vnd.api+json', 'HTTP_ACCEPT' => 'application/vnd.api+json']), catch: false);
            self::assertSame(204, $response->getStatusCode());
        } finally {
            $kernel->shutdown();
        }
    }

    public static function typedProviderModes(): iterable
    {
        yield 'custom provider' => ['rc8'];
        yield 'Doctrine provider with non-ORM typed resources' => ['doctrine_typed'];
    }

    public function testDoctrineAtomicDoesNotPretendToProtectCustomPersister(): void
    {
        $kernel = new RcKernel('doctrine_typed', false);
        try {
            $response = $kernel->handle(Request::create('/api/operations', 'POST', server: ['CONTENT_TYPE' => 'application/vnd.api+json;ext="https://jsonapi.org/ext/atomic"', 'HTTP_ACCEPT' => 'application/vnd.api+json;ext="https://jsonapi.org/ext/atomic"'], content: json_encode(['atomic:operations' => [['op' => 'add', 'data' => ['type' => 'rc-memory', 'attributes' => ['title' => 'Command']]]]], \JSON_THROW_ON_ERROR)));
            self::assertSame(409, $response->getStatusCode(), $response->getContent());
            $document = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            self::assertSame('unsupported-transaction-boundary', $document['errors'][0]['code']);
        } finally {
            $kernel->shutdown();
        }
    }

    #[DataProvider('nativeRoutes')]
    public function testConfiguredRoutesNegotiateAfterRouterAndControllerAttributes(string $path, string $accept, string $mime): void
    {
        $kernel = new RcKernel('test', false);
        try {
            $response = $kernel->handle(Request::create($path, server: ['HTTP_ACCEPT' => $accept]), catch: false);
            self::assertSame(200, $response->getStatusCode(), $response->getContent());
            self::assertStringStartsWith($mime, $response->headers->get('Content-Type'));
        } finally {
            $kernel->shutdown();
        }
    }

    public static function nativeRoutes(): iterable
    {
        yield ['/_jsonapi/openapi.json', 'application/vnd.oai.openapi+json', 'application/vnd.oai.openapi+json'];
        yield ['/_jsonapi/openapi.json', 'application/json', 'application/vnd.oai.openapi+json'];
        yield ['/_jsonapi/docs', 'text/html', 'text/html'];
        yield ['/_jsonapi/schemas', 'application/schema+json', 'application/schema+json'];
        yield ['/route-channel', 'text/plain', 'text/plain'];
        yield ['/attribute-channel', 'text/html', 'text/html'];
    }

    public function testEndpointExamplesAreIncludedByRealControllerDiscovery(): void
    {
        $kernel = new RcKernel('test', false);
        try {
            $response = $kernel->handle(Request::create('/_jsonapi/openapi.json', server: ['HTTP_ACCEPT' => 'application/json']), catch: false);
            self::assertSame(200, $response->getStatusCode(), $response->getContent());
            $spec = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            $example = $spec['paths']['/route-channel']['get']['requestBody']['content']['application/json']['examples']['sample'];
            self::assertSame('Example input', $example['summary']);
            self::assertSame(['title' => 'Example'], $example['value']);
            self::assertSame('Public example', $example['description']);
        } finally {
            $kernel->shutdown();
        }
    }

    public function testConstructorInjectedProfileAndValidationCommandWorkInCompiledBundle(): void
    {
        $kernel = new RcKernel('test', false);
        try {
            $kernel->boot();
            $registry = $kernel->getContainer()->get('test.service_container')->get(ProfileRegistry::class);
            self::assertTrue($registry->has('urn:test:injected'));
            $application = new Application($kernel);
            $command = $application->find('jsonapi:validate-profiles');
            $tester = new CommandTester($command);
            self::assertSame(0, $tester->execute([]));
        } finally {
            $kernel->shutdown();
        }
    }
}
