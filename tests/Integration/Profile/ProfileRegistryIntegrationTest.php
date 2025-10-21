<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Profile;

use AlexFigures\Symfony\Bridge\Symfony\EventSubscriber\ProfileNegotiationSubscriber;
use AlexFigures\Symfony\Profile\Builtin\AuditTrailProfile;
use AlexFigures\Symfony\Profile\Builtin\RelationshipCountsProfile;
use AlexFigures\Symfony\Profile\Builtin\SoftDeleteProfile;
use AlexFigures\Symfony\Profile\Descriptor\ProfileDescriptor;
use AlexFigures\Symfony\Profile\Negotiation\ProfileNegotiator;
use AlexFigures\Symfony\Profile\ProfileContext;
use AlexFigures\Symfony\Profile\ProfileInterface;
use AlexFigures\Symfony\Profile\ProfileRegistry;
use AlexFigures\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Integration tests for profile registry with real database operations.
 *
 * Tests profile registration, activation mechanisms (default, per-type, per-request),
 * and priority handling in the context of actual database queries.
 */
#[CoversClass(ProfileRegistry::class)]
#[CoversClass(ProfileNegotiator::class)]
#[CoversClass(ProfileContext::class)]
final class ProfileRegistryIntegrationTest extends DoctrineIntegrationTestCase
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

        $this->profileRegistry = new ProfileRegistry();
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

    public function testProfileRegistration(): void
    {
        // Create custom profile
        $profile = new class () implements ProfileInterface {
            public function uri(): string
            {
                return 'urn:test:custom-profile';
            }

            public function descriptor(): ProfileDescriptor
            {
                return new ProfileDescriptor($this->uri(), 'Custom Profile', '1.0');
            }

            public function hooks(): iterable
            {
                return [];
            }
        };

        // Register profile
        $this->profileRegistry->register($profile);

        // Verify profile is registered
        $registered = $this->profileRegistry->get($profile->uri());
        self::assertNotNull($registered);
        self::assertSame($profile, $registered);

        // Verify database operations still work
        $criteria = new \AlexFigures\Symfony\Query\Criteria();
        $article = $this->repository->findOne('articles', 'article-1', $criteria);
        self::assertNotNull($article);
    }

    public function testDefaultProfileActivation(): void
    {
        // Register built-in profile
        $softDelete = new SoftDeleteProfile();
        $this->profileRegistry->register($softDelete);

        // Create negotiator with default profile
        $negotiator = new ProfileNegotiator(
            $this->profileRegistry,
            [SoftDeleteProfile::URI],
            [],
            []
        );
        $subscriber = new ProfileNegotiationSubscriber($negotiator);

        // Create request without explicit profile
        $request = Request::create('/articles', 'GET');
        $request->headers->set('Accept', 'application/vnd.api+json');

        // Process request
        $kernel = $this->createMockKernel();
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);

        // Verify default profile is active
        $context = ProfileContext::fromRequest($request);
        self::assertInstanceOf(ProfileContext::class, $context);
        self::assertTrue($context->has(SoftDeleteProfile::URI));

        // Verify database query works with default profile
        $criteria = new \AlexFigures\Symfony\Query\Criteria();
        $article = $this->repository->findOne('articles', 'article-1', $criteria);
        self::assertNotNull($article);
        self::assertInstanceOf(Article::class, $article);
    }

    public function testMultipleDefaultProfiles(): void
    {
        // Register multiple built-in profiles
        $this->profileRegistry->register(new SoftDeleteProfile());
        $this->profileRegistry->register(new AuditTrailProfile());

        // Create negotiator with multiple default profiles
        $negotiator = new ProfileNegotiator(
            $this->profileRegistry,
            [SoftDeleteProfile::URI, AuditTrailProfile::URI],
            [],
            []
        );
        $subscriber = new ProfileNegotiationSubscriber($negotiator);

        // Create request
        $request = Request::create('/articles', 'GET');
        $request->headers->set('Accept', 'application/vnd.api+json');

        // Process request
        $kernel = $this->createMockKernel();
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);

        // Verify both default profiles are active
        $context = ProfileContext::fromRequest($request);
        self::assertInstanceOf(ProfileContext::class, $context);
        self::assertTrue($context->has(SoftDeleteProfile::URI));
        self::assertTrue($context->has(AuditTrailProfile::URI));

        // Verify database operations work
        $criteria = new \AlexFigures\Symfony\Query\Criteria();
        $articles = $this->repository->findCollection('articles', $criteria);
        self::assertNotEmpty($articles);
    }

    public function testPerResourceTypeActivation(): void
    {
        // Register profiles
        $this->profileRegistry->register(new SoftDeleteProfile());
        $this->profileRegistry->register(new RelationshipCountsProfile());

        // Create negotiator with per-type profiles
        $negotiator = new ProfileNegotiator(
            $this->profileRegistry,
            [],
            [
                'articles' => [SoftDeleteProfile::URI],
                'authors' => [RelationshipCountsProfile::URI],
            ],
            []
        );
        $subscriber = new ProfileNegotiationSubscriber($negotiator);

        // Create request
        $request = Request::create('/articles', 'GET');
        $request->headers->set('Accept', 'application/vnd.api+json');

        // Process request
        $kernel = $this->createMockKernel();
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);

        // Verify per-type profiles
        $context = ProfileContext::fromRequest($request);
        self::assertInstanceOf(ProfileContext::class, $context);

        $articleProfiles = $context->profilesForType('articles');
        $articleUris = array_map(fn($p) => $p->uri(), $articleProfiles);
        self::assertContains(SoftDeleteProfile::URI, $articleUris);
        self::assertNotContains(RelationshipCountsProfile::URI, $articleUris);

        $authorProfiles = $context->profilesForType('authors');
        $authorUris = array_map(fn($p) => $p->uri(), $authorProfiles);
        self::assertContains(RelationshipCountsProfile::URI, $authorUris);
        self::assertNotContains(SoftDeleteProfile::URI, $authorUris);

        // Verify database queries work for both types
        $criteria = new \AlexFigures\Symfony\Query\Criteria();
        $article = $this->repository->findOne('articles', 'article-1', $criteria);
        self::assertNotNull($article);

        $author = $this->repository->findOne('authors', 'author-1', $criteria);
        self::assertNotNull($author);
    }

    public function testPerRequestActivationOverridesDefaults(): void
    {
        // Register profiles
        $this->profileRegistry->register(new SoftDeleteProfile());
        $this->profileRegistry->register(new AuditTrailProfile());

        // Create negotiator with default profile
        $negotiator = new ProfileNegotiator(
            $this->profileRegistry,
            [SoftDeleteProfile::URI],
            [],
            []
        );
        $subscriber = new ProfileNegotiationSubscriber($negotiator);

        // Create request with different profile in Accept header
        $request = Request::create('/articles', 'GET');
        $request->headers->set('Accept', 'application/vnd.api+json; profile="' . AuditTrailProfile::URI . '"');

        // Process request
        $kernel = $this->createMockKernel();
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);

        // Verify both default and request profiles are active
        $context = ProfileContext::fromRequest($request);
        self::assertInstanceOf(ProfileContext::class, $context);
        self::assertTrue($context->has(SoftDeleteProfile::URI));
        self::assertTrue($context->has(AuditTrailProfile::URI));

        // Verify database query works
        $criteria = new \AlexFigures\Symfony\Query\Criteria();
        $article = $this->repository->findOne('articles', 'article-1', $criteria);
        self::assertNotNull($article);
    }

    public function testProfilePriorityAndMerging(): void
    {
        // Register all built-in profiles
        $this->profileRegistry->register(new SoftDeleteProfile());
        $this->profileRegistry->register(new AuditTrailProfile());
        $this->profileRegistry->register(new RelationshipCountsProfile());

        // Create negotiator with profiles from all sources
        $negotiator = new ProfileNegotiator(
            $this->profileRegistry,
            [SoftDeleteProfile::URI], // default
            ['articles' => [AuditTrailProfile::URI]], // per-type
            []
        );
        $subscriber = new ProfileNegotiationSubscriber($negotiator);

        // Create request with additional profile
        $request = Request::create('/articles', 'GET');
        $request->headers->set('Accept', 'application/vnd.api+json; profile="' . RelationshipCountsProfile::URI . '"');

        // Process request
        $kernel = $this->createMockKernel();
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);

        // Verify all profiles are merged and active
        $context = ProfileContext::fromRequest($request);
        self::assertInstanceOf(ProfileContext::class, $context);

        $articleProfiles = $context->profilesForType('articles');
        $articleUris = array_map(fn($p) => $p->uri(), $articleProfiles);

        // All three profiles should be active for articles
        self::assertContains(SoftDeleteProfile::URI, $articleUris);
        self::assertContains(AuditTrailProfile::URI, $articleUris);
        self::assertContains(RelationshipCountsProfile::URI, $articleUris);

        // Verify database operations work with all profiles
        $criteria = new \AlexFigures\Symfony\Query\Criteria();
        $article = $this->repository->findOne('articles', 'article-1', $criteria);
        self::assertNotNull($article);

        $articles = $this->repository->findCollection('articles', $criteria);
        self::assertNotEmpty($articles);
    }

    public function testProfileContextAccessMethods(): void
    {
        // Register profiles
        $softDelete = new SoftDeleteProfile();
        $auditTrail = new AuditTrailProfile();
        $this->profileRegistry->register($softDelete);
        $this->profileRegistry->register($auditTrail);

        // Create context with profiles
        $context = new ProfileContext([
            SoftDeleteProfile::URI => $softDelete,
            AuditTrailProfile::URI => $auditTrail,
        ]);

        // Test has() method
        self::assertTrue($context->has(SoftDeleteProfile::URI));
        self::assertTrue($context->has(AuditTrailProfile::URI));
        self::assertFalse($context->has('urn:unknown:profile'));

        // Test profile() method
        $retrieved = $context->profile(SoftDeleteProfile::URI);
        self::assertNotNull($retrieved);
        self::assertSame($softDelete, $retrieved);

        // Test profiles() method
        $allProfiles = $context->profiles();
        self::assertCount(2, $allProfiles);

        // Test activeUris() method
        $uris = $context->activeUris();
        self::assertContains(SoftDeleteProfile::URI, $uris);
        self::assertContains(AuditTrailProfile::URI, $uris);

        // Verify database operations work
        $criteria = new \AlexFigures\Symfony\Query\Criteria();
        $article = $this->repository->findOne('articles', 'article-1', $criteria);
        self::assertNotNull($article);
    }

    public function testEmptyProfileContext(): void
    {
        // Create empty context
        $context = new ProfileContext([]);

        // Verify empty context behavior
        self::assertFalse($context->has(SoftDeleteProfile::URI));
        self::assertNull($context->profile(SoftDeleteProfile::URI));
        self::assertEmpty($context->profiles());
        self::assertEmpty($context->activeUris());

        // Verify database operations still work without profiles
        $criteria = new \AlexFigures\Symfony\Query\Criteria();
        $article = $this->repository->findOne('articles', 'article-1', $criteria);
        self::assertNotNull($article);

        $articles = $this->repository->findCollection('articles', $criteria);
        self::assertNotEmpty($articles);
    }

    private function createMockKernel(): HttpKernelInterface
    {
        return new class () implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): \Symfony\Component\HttpFoundation\Response
            {
                return new \Symfony\Component\HttpFoundation\Response();
            }
        };
    }
}

