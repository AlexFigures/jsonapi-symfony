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
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Comment;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Tag;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Integration tests for built-in profiles with real database operations.
 *
 * Tests that built-in profiles (SoftDelete, AuditTrail, RelationshipCounts)
 * work correctly with actual database queries and entity operations.
 */
#[CoversClass(SoftDeleteProfile::class)]
#[CoversClass(AuditTrailProfile::class)]
#[CoversClass(RelationshipCountsProfile::class)]
final class BuiltinProfilesIntegrationTest extends DoctrineIntegrationTestCase
{
    private ProfileRegistry $profileRegistry;
    private SoftDeleteProfile $softDeleteProfile;
    private AuditTrailProfile $auditTrailProfile;
    private RelationshipCountsProfile $relationshipCountsProfile;

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

        // Initialize built-in profiles
        $this->softDeleteProfile = new SoftDeleteProfile();
        $this->auditTrailProfile = new AuditTrailProfile();
        $this->relationshipCountsProfile = new RelationshipCountsProfile();

        $this->profileRegistry = new ProfileRegistry([
            $this->softDeleteProfile,
            $this->auditTrailProfile,
            $this->relationshipCountsProfile,
        ]);

        $this->seedTestData();
    }

    private function seedTestData(): void
    {
        $author = new Author();
        $author->setId('author-1');
        $author->setName('John Doe');
        $author->setEmail('john@example.com');

        $tag1 = new Tag();
        $tag1->setId('tag-1');
        $tag1->setName('PHP');

        $tag2 = new Tag();
        $tag2->setId('tag-2');
        $tag2->setName('Symfony');

        $article = new Article();
        $article->setId('article-1');
        $article->setTitle('Test Article');
        $article->setContent('Test content');
        $article->setAuthor($author);
        $article->addTag($tag1);
        $article->addTag($tag2);

        $comment1 = new Comment();
        $comment1->setContent('First comment');
        $comment1->setAuthorName('User One');
        $comment1->setRating(5);

        $comment2 = new Comment();
        $comment2->setContent('Second comment');
        $comment2->setAuthorName('User Two');
        $comment2->setRating(4);

        $this->em->persist($author);
        $this->em->persist($tag1);
        $this->em->persist($tag2);
        $this->em->persist($article);
        $this->em->persist($comment1);
        $this->em->persist($comment2);
        $this->em->flush();
        $this->em->clear();
    }

    public function testSoftDeleteProfileRegistration(): void
    {
        // Verify profile is registered
        $profile = $this->profileRegistry->get(SoftDeleteProfile::URI);
        self::assertNotNull($profile);
        self::assertInstanceOf(SoftDeleteProfile::class, $profile);

        // Verify descriptor
        $descriptor = $profile->descriptor();
        self::assertSame(SoftDeleteProfile::URI, $descriptor->uri);
        self::assertSame('Soft Delete', $descriptor->name);
        self::assertSame('1.0', $descriptor->version);
        self::assertContains('query', $descriptor->capabilities);
        self::assertContains('write', $descriptor->capabilities);
    }

    public function testAuditTrailProfileRegistration(): void
    {
        // Verify profile is registered
        $profile = $this->profileRegistry->get(AuditTrailProfile::URI);
        self::assertNotNull($profile);
        self::assertInstanceOf(AuditTrailProfile::class, $profile);

        // Verify descriptor
        $descriptor = $profile->descriptor();
        self::assertSame(AuditTrailProfile::URI, $descriptor->uri);
        self::assertSame('Audit Trail', $descriptor->name);
        self::assertSame('1.0', $descriptor->version);
        self::assertContains('document-meta', $descriptor->capabilities);
        self::assertContains('write', $descriptor->capabilities);
    }

    public function testRelationshipCountsProfileRegistration(): void
    {
        // Verify profile is registered
        $profile = $this->profileRegistry->get(RelationshipCountsProfile::URI);
        self::assertNotNull($profile);
        self::assertInstanceOf(RelationshipCountsProfile::class, $profile);

        // Verify descriptor
        $descriptor = $profile->descriptor();
        self::assertSame(RelationshipCountsProfile::URI, $descriptor->uri);
        self::assertSame('Relationship Counts', $descriptor->name);
        self::assertSame('1.0', $descriptor->version);
        self::assertContains('document-relationships', $descriptor->capabilities);
    }

    public function testSoftDeleteProfileActivation(): void
    {
        // Create negotiator with soft-delete enabled by default
        $negotiator = new ProfileNegotiator(
            $this->profileRegistry,
            [SoftDeleteProfile::URI],
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

        // Verify profile is active
        $context = ProfileContext::fromRequest($request);
        self::assertInstanceOf(ProfileContext::class, $context);
        self::assertTrue($context->has(SoftDeleteProfile::URI));

        // Verify database query works with profile active
        $criteria = new \AlexFigures\Symfony\Query\Criteria();
        $article = $this->repository->findOne('articles', 'article-1', $criteria);
        self::assertNotNull($article);
        self::assertInstanceOf(Article::class, $article);
    }

    public function testAuditTrailProfileActivation(): void
    {
        // Create negotiator with audit-trail enabled per-type
        $negotiator = new ProfileNegotiator(
            $this->profileRegistry,
            [],
            ['articles' => [AuditTrailProfile::URI]],
            []
        );
        $subscriber = new ProfileNegotiationSubscriber($negotiator);

        // Create request
        $request = Request::create('/articles', 'POST');
        $request->headers->set('Accept', 'application/vnd.api+json');
        $request->headers->set('Content-Type', 'application/vnd.api+json');

        // Process request
        $kernel = $this->createMockKernel();
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);

        // Verify profile is active for articles
        $context = ProfileContext::fromRequest($request);
        self::assertInstanceOf(ProfileContext::class, $context);

        $articleProfiles = $context->profilesForType('articles');
        $articleUris = array_map(fn ($p) => $p->uri(), $articleProfiles);
        self::assertContains(AuditTrailProfile::URI, $articleUris);

        // Verify database operations work
        $criteria = new \AlexFigures\Symfony\Query\Criteria();
        $article = $this->repository->findOne('articles', 'article-1', $criteria);
        self::assertNotNull($article);
    }

    public function testRelationshipCountsProfileActivation(): void
    {
        // Create negotiator with relationship-counts enabled via request
        $negotiator = new ProfileNegotiator($this->profileRegistry);
        $subscriber = new ProfileNegotiationSubscriber($negotiator);

        // Create request with profile in Accept header
        $request = Request::create('/articles', 'GET');
        $request->headers->set('Accept', 'application/vnd.api+json; profile="' . RelationshipCountsProfile::URI . '"');

        // Process request
        $kernel = $this->createMockKernel();
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);

        // Verify profile is active
        $context = ProfileContext::fromRequest($request);
        self::assertInstanceOf(ProfileContext::class, $context);
        self::assertTrue($context->has(RelationshipCountsProfile::URI));

        // Verify database query with relationships works
        $criteria = new \AlexFigures\Symfony\Query\Criteria();
        $article = $this->repository->findOne('articles', 'article-1', $criteria);
        self::assertNotNull($article);
        self::assertInstanceOf(Article::class, $article);

        // Verify relationships are loaded
        $tags = $article->getTags();
        self::assertCount(2, $tags);
    }

    public function testMultipleBuiltinProfilesActivation(): void
    {
        // Create negotiator with multiple built-in profiles
        $negotiator = new ProfileNegotiator(
            $this->profileRegistry,
            [SoftDeleteProfile::URI, AuditTrailProfile::URI],
            [],
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

        // Verify all profiles are active
        $context = ProfileContext::fromRequest($request);
        self::assertInstanceOf(ProfileContext::class, $context);
        self::assertTrue($context->has(SoftDeleteProfile::URI));
        self::assertTrue($context->has(AuditTrailProfile::URI));
        self::assertTrue($context->has(RelationshipCountsProfile::URI));

        // Verify database operations work with all profiles
        $criteria = new \AlexFigures\Symfony\Query\Criteria();
        $article = $this->repository->findOne('articles', 'article-1', $criteria);
        self::assertNotNull($article);

        $articles = $this->repository->findCollection('articles', $criteria);
        self::assertNotEmpty($articles);
    }

    public function testBuiltinProfilesPerResourceType(): void
    {
        // Create negotiator with different profiles per resource type
        $negotiator = new ProfileNegotiator(
            $this->profileRegistry,
            [],
            [
                'articles' => [SoftDeleteProfile::URI, RelationshipCountsProfile::URI],
                'authors' => [AuditTrailProfile::URI],
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

        // Verify profiles for articles
        $context = ProfileContext::fromRequest($request);
        self::assertNotNull($context);
        $articleProfiles = $context->profilesForType('articles');
        $articleUris = array_map(fn ($p) => $p->uri(), $articleProfiles);
        self::assertContains(SoftDeleteProfile::URI, $articleUris);
        self::assertContains(RelationshipCountsProfile::URI, $articleUris);
        self::assertNotContains(AuditTrailProfile::URI, $articleUris);

        // Verify profiles for authors
        $authorProfiles = $context->profilesForType('authors');
        $authorUris = array_map(fn ($p) => $p->uri(), $authorProfiles);
        self::assertContains(AuditTrailProfile::URI, $authorUris);
        self::assertNotContains(SoftDeleteProfile::URI, $authorUris);

        // Verify database queries work
        $criteria = new \AlexFigures\Symfony\Query\Criteria();
        $article = $this->repository->findOne('articles', 'article-1', $criteria);
        self::assertNotNull($article);

        $author = $this->repository->findOne('authors', 'author-1', $criteria);
        self::assertNotNull($author);
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
