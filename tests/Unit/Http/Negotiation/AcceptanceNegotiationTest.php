<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Unit\Http\Negotiation;

use AlexFigures\JsonApi\Atomic\AtomicConfig;
use AlexFigures\JsonApi\Bridge\Symfony\EventSubscriber\ContentNegotiationSubscriber;
use AlexFigures\JsonApi\Bridge\Symfony\Negotiation\ChannelScopeMatcher;
use AlexFigures\JsonApi\Bridge\Symfony\Negotiation\ConfigMediaTypePolicyProvider;
use AlexFigures\JsonApi\Http\Exception\JsonApiHttpException;
use AlexFigures\JsonApi\Http\Negotiation\MediaType;
use AlexFigures\JsonApi\Http\Negotiation\MediaTypeNegotiator;
use AlexFigures\JsonApi\Http\Negotiation\ParsedMediaType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class AcceptanceNegotiationTest extends TestCase
{
    #[DataProvider('acceptHeaders')]
    public function testAcceptCandidatesAreIndependent(string $accept, int $status): void
    {
        $subscriber = new ContentNegotiationSubscriber(true, $this->policy());
        $request = Request::create('/api/resources', server: ['HTTP_ACCEPT' => $accept]);
        try {
            $subscriber->onKernelRequest(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
            self::assertSame(200, $status);
        } catch (JsonApiHttpException $exception) {
            self::assertSame($status, $exception->getStatusCode());
        }
    }

    public static function acceptHeaders(): iterable
    {
        yield 'invalid then valid' => [MediaType::JSON_API . ';foo=bar,' . MediaType::JSON_API, 200];
        yield 'valid then invalid' => [MediaType::JSON_API . ',' . MediaType::JSON_API . ';foo=bar', 200];
        yield 'extension fallback' => [MediaType::JSON_API . ';ext="https://unknown.test/ext",' . MediaType::JSON_API, 200];
        yield 'quality one' => [MediaType::JSON_API . ';q=1', 200];
        yield 'quality fractional' => [MediaType::JSON_API . ';q=0.8', 200];
        yield 'quality zero' => [MediaType::JSON_API . ';q=0', 406];
        yield 'zero with valid' => [MediaType::JSON_API . ';q=0,' . MediaType::JSON_API . ';profile="https://example.test/profile";q=0.8', 200];
        yield 'case insensitive' => ['Application/Vnd.Api+Json;PROFILE="https://example.test/profile"', 200];
        yield 'explicit zero overrides wildcard' => [MediaType::JSON_API . ';q=0,*/*;q=1', 406];
        yield 'Accept extension metadata' => [MediaType::JSON_API . ';q=0.8;trace=on', 200];
        yield 'wildcard' => ['application/*;q=0.5', 200];
    }

    #[DataProvider('atomicHeaders')]
    public function testAtomicAndStrictNegotiationShareMediaRules(string $contentType, string $accept, int $status): void
    {
        $request = Request::create('/api/operations', 'POST', server: ['CONTENT_TYPE' => $contentType, 'HTTP_ACCEPT' => $accept]);
        $subscriber = new ContentNegotiationSubscriber(true, $this->policy(), true);
        $negotiator = new MediaTypeNegotiator(new AtomicConfig(enabled: true), $this->policy());
        try {
            $subscriber->onKernelRequest(new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
            $negotiator->assertAtomicExt($request);
            self::assertSame(200, $status);
        } catch (JsonApiHttpException $exception) {
            self::assertSame($status, $exception->getStatusCode());
        }
    }

    public static function atomicHeaders(): iterable
    {
        yield 'strict atomic supported' => [MediaType::JSON_API_ATOMIC, MediaType::JSON_API_ATOMIC, 200];
        yield 'wrong base' => ['text/plain;ext="https://jsonapi.org/ext/atomic"', MediaType::JSON_API_ATOMIC, 415];
        yield 'unsupported parameter' => [MediaType::JSON_API_ATOMIC . ';charset=utf-8', MediaType::JSON_API_ATOMIC, 415];
        yield 'additional unknown extension' => [MediaType::JSON_API . ';ext="https://jsonapi.org/ext/atomic https://unknown.test/ext"', MediaType::JSON_API_ATOMIC, 415];
        yield 'atomic quality zero' => [MediaType::JSON_API_ATOMIC, MediaType::JSON_API_ATOMIC . ';q=0', 406];
        yield 'atomic quality valid' => [MediaType::JSON_API_ATOMIC, MediaType::JSON_API_ATOMIC . ';q=0.8', 200];
    }

    public function testQuotedSeparatorsDoNotSplitCandidates(): void
    {
        $parsed = ParsedMediaType::parse('Application/Vnd.Api+Json;profile="https://example.test/a,b;c";q=0.8, application/*;q=0.2', true);
        self::assertCount(2, $parsed);
        self::assertSame('https://example.test/a,b;c', $parsed[0]->parameters['profile']);
        self::assertSame(0.8, $parsed[0]->quality);
        self::assertTrue($parsed[0]->validJsonApi());
    }

    private function policy(): ConfigMediaTypePolicyProvider
    {
        return new ConfigMediaTypePolicyProvider(['default' => ['request' => ['allowed' => [MediaType::JSON_API]], 'response' => ['default' => MediaType::JSON_API, 'negotiable' => [MediaType::JSON_API]]]], new ChannelScopeMatcher());
    }
}
