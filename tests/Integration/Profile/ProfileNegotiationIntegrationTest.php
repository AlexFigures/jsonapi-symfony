<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Profile;

use AlexFigures\Symfony\Bridge\Symfony\EventSubscriber\ProfileNegotiationSubscriber;
use AlexFigures\Symfony\Profile\Builtin\AuditTrailProfile;
use AlexFigures\Symfony\Profile\Builtin\RelationshipCountsProfile;
use AlexFigures\Symfony\Profile\Builtin\SoftDeleteProfile;
use AlexFigures\Symfony\Profile\Negotiation\ProfileNegotiator;
use AlexFigures\Symfony\Profile\ProfileContext;
use AlexFigures\Symfony\Profile\ProfileRegistry;
use AlexFigures\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Integration tests for profile negotiation with real database operations.
 *
 * Tests profile negotiation from Accept/Content-Type headers and response header reflection
 * in the context of actual database queries and entity operations.
 */
#[CoversClass(ProfileNegotiator::class)]
#[CoversClass(ProfileNegotiationSubscriber::class)]
#[CoversClass(ProfileContext::class)]
final class ProfileNegotiationIntegrationTest extends DoctrineIntegrationTestCase
{
    private ProfileRegistry $profileRegistry;

    protected function getDatabaseUrl(): string
    {
        $url = $_ENV['DATABASE_URL_PGSQL'] ?? 'postgresql://jsonapi:jsonapi@postgres:5432/jsonapi_test?serverVersion=16&charset=utf8';
        assert(is_string($url));
        return $url;
    }

    protected function getPlatform(): string
    {
        return 'postgresql';
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize profile registry with built-in profiles
        $this->profileRegistry = new ProfileRegistry([
            new SoftDeleteProfile(),
            new AuditTrailProfile(),
            new RelationshipCountsProfile(),
        ]);

        // Seed test data
        $this->seedTestData();
    }

    private function seedTestData(): void
    {
        $author = new Author();
        $author->setId('author-1');
        $author->setName('John Doe');
        $author->setEmail('john@example.com');

        $article = new Article();
        $article->setId('article-1');
        $article->setTitle('Test Article');
        $article->setContent('Test content');
        $article->setAuthor($author);

        $this->em->persist($author);
        $this->em->persist($article);
        $this->em->flush();
        $this->em->clear();
    }

    public function testProfileNegotiationFromAcceptHeader(): void
    {
        // Create negotiator with soft-delete profile
        $negotiator = new ProfileNegotiator(
            $this->profileRegistry,
            [],
            [],
            []
        );
        $subscriber = new ProfileNegotiationSubscriber($negotiator);

        // Create request with profile in Accept header
        $request = Request::create('/articles', 'GET');
        $request->headers->set('Accept', 'application/vnd.api+json; profile="' . SoftDeleteProfile::URI . '"');

        // Process request
        $kernel = $this->createMockKernel();
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);

        // Get profile context from request
        $context = ProfileContext::fromRequest($request);
        self::assertInstanceOf(ProfileContext::class, $context);
        self::assertTrue($context->has(SoftDeleteProfile::URI));

        // Verify we can query database with profile active
        $criteria = new \AlexFigures\Symfony\Query\Criteria();
        $article = $this->repository->findOne('articles', 'article-1', $criteria);
        self::assertNotNull($article);
        self::assertInstanceOf(Article::class, $article);
    }

    public function testProfileNegotiationFromContentTypeHeader(): void
    {
        // Create negotiator
        $negotiator = new ProfileNegotiator($this->profileRegistry);
        $subscriber = new ProfileNegotiationSubscriber($negotiator);

        // Create POST request with profile in Content-Type
        $request = Request::create('/articles', 'POST');
        $request->headers->set('Content-Type', 'application/vnd.api+json; profile="' . AuditTrailProfile::URI . '"');
        $request->headers->set('Accept', 'application/vnd.api+json');

        // Process request
        $kernel = $this->createMockKernel();
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);

        // Get profile context
        $context = ProfileContext::fromRequest($request);
        self::assertInstanceOf(ProfileContext::class, $context);
        self::assertTrue($context->has(AuditTrailProfile::URI));

        // Verify database operations work with profile
        $criteria = new \AlexFigures\Symfony\Query\Criteria();
        $article = $this->repository->findOne('articles', 'article-1', $criteria);
        self::assertNotNull($article);
    }

    public function testProfileNegationSyntax(): void
    {
        // Create negotiator with default profile
        $negotiator = new ProfileNegotiator(
            $this->profileRegistry,
            [SoftDeleteProfile::URI],
            [],
            []
        );
        $subscriber = new ProfileNegotiationSubscriber($negotiator);

        // Create request with negation to disable default profile
        $request = Request::create('/articles', 'GET');
        $request->headers->set('Accept', 'application/vnd.api+json; profile="!' . SoftDeleteProfile::URI . ' ' . AuditTrailProfile::URI . '"');

        // Process request
        $kernel = $this->createMockKernel();
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);

        // Verify soft-delete is disabled, audit-trail is enabled
        $context = ProfileContext::fromRequest($request);
        self::assertInstanceOf(ProfileContext::class, $context);
        self::assertFalse($context->has(SoftDeleteProfile::URI));
        self::assertTrue($context->has(AuditTrailProfile::URI));

        // Database operations should still work
        $criteria = new \AlexFigures\Symfony\Query\Criteria();
        $article = $this->repository->findOne('articles', 'article-1', $criteria);
        self::assertNotNull($article);
    }

    public function testResponseHeadersIncludeActiveProfiles(): void
    {
        // Create negotiator with profile enabled
        $negotiator = new ProfileNegotiator(
            $this->profileRegistry,
            [RelationshipCountsProfile::URI],
            [],
            ['echo_profiles_in_content_type' => true, 'link_header' => true]
        );
        $subscriber = new ProfileNegotiationSubscriber($negotiator);

        // Create request
        $request = Request::create('/articles', 'GET');
        $request->headers->set('Accept', 'application/vnd.api+json');

        // Process request
        $kernel = $this->createMockKernel();
        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($requestEvent);

        // Create response
        $response = new Response('', 200, ['Content-Type' => 'application/vnd.api+json']);

        // Process response
        $responseEvent = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($responseEvent);

        // Verify Content-Type includes profile
        $contentType = $response->headers->get('Content-Type');
        self::assertNotNull($contentType);
        self::assertStringContainsString(RelationshipCountsProfile::URI, $contentType);

        // Verify Link header includes profile
        $linkHeader = $response->headers->get('Link');
        self::assertNotNull($linkHeader);
        self::assertStringContainsString('<' . RelationshipCountsProfile::URI . '>', $linkHeader);
        self::assertStringContainsString('rel="profile"', $linkHeader);
    }

    public function testMultipleProfilesFromDifferentSources(): void
    {
        // Create negotiator with default profile and per-type profiles
        $negotiator = new ProfileNegotiator(
            $this->profileRegistry,
            [SoftDeleteProfile::URI],
            ['articles' => [RelationshipCountsProfile::URI]],
            []
        );
        $subscriber = new ProfileNegotiationSubscriber($negotiator);

        // Create request with additional profile in Accept
        $request = Request::create('/articles', 'GET');
        $request->headers->set('Accept', 'application/vnd.api+json; profile="' . AuditTrailProfile::URI . '"');

        // Process request
        $kernel = $this->createMockKernel();
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);

        // Verify all profiles are active
        $context = ProfileContext::fromRequest($request);
        self::assertInstanceOf(ProfileContext::class, $context);
        self::assertTrue($context->has(SoftDeleteProfile::URI));
        self::assertTrue($context->has(AuditTrailProfile::URI));

        // Verify per-type profiles
        $articleProfiles = $context->profilesForType('articles');
        $articleUris = array_map(fn($p) => $p->uri(), $articleProfiles);
        self::assertContains(SoftDeleteProfile::URI, $articleUris);
        self::assertContains(AuditTrailProfile::URI, $articleUris);
        self::assertContains(RelationshipCountsProfile::URI, $articleUris);

        // Database operations should work
        $criteria = new \AlexFigures\Symfony\Query\Criteria();
        $articles = $this->repository->findCollection('articles', $criteria);
        self::assertNotEmpty($articles);
    }

    private function createMockKernel(): HttpKernelInterface
    {
        return new class () implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }
        };
    }
}

