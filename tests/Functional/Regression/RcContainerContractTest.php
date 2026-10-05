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
