<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Resource;

use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use AlexFigures\Symfony\Resource\Attribute\Relationship;
use AlexFigures\Symfony\Resource\Metadata\RelationshipLinkingPolicy;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResourceRegistry::class)]
final class RelationshipLinkingPolicyTest extends TestCase
{
    public function testRelationshipWithDefaultLinkingPolicy(): void
    {
        $registry = new ResourceRegistry([ArticleWithDefaultPolicy::class]);

        $metadata = $registry->getByType('articles');

        self::assertArrayHasKey('author', $metadata->relationships);
        $relationship = $metadata->relationships['author'];
        self::assertSame(RelationshipLinkingPolicy::REFERENCE, $relationship->linkingPolicy);
    }

    public function testRelationshipWithVerifyLinkingPolicyEnum(): void
    {
        $registry = new ResourceRegistry([ArticleWithVerifyPolicyEnum::class]);

        $metadata = $registry->getByType('articles');

        self::assertArrayHasKey('author', $metadata->relationships);
        $relationship = $metadata->relationships['author'];
        self::assertSame(RelationshipLinkingPolicy::VERIFY, $relationship->linkingPolicy);
    }

    public function testRelationshipWithVerifyLinkingPolicyString(): void
    {
        $registry = new ResourceRegistry([ArticleWithVerifyPolicyString::class]);

        $metadata = $registry->getByType('articles');

        self::assertArrayHasKey('author', $metadata->relationships);
        $relationship = $metadata->relationships['author'];
        self::assertSame(RelationshipLinkingPolicy::VERIFY, $relationship->linkingPolicy);
    }

    public function testRelationshipWithReferenceLinkingPolicyEnum(): void
    {
        $registry = new ResourceRegistry([ArticleWithReferencePolicyEnum::class]);

        $metadata = $registry->getByType('articles');

        self::assertArrayHasKey('author', $metadata->relationships);
        $relationship = $metadata->relationships['author'];
        self::assertSame(RelationshipLinkingPolicy::REFERENCE, $relationship->linkingPolicy);
    }

    public function testRelationshipWithReferenceLinkingPolicyString(): void
    {
        $registry = new ResourceRegistry([ArticleWithReferencePolicyString::class]);

        $metadata = $registry->getByType('articles');

        self::assertArrayHasKey('author', $metadata->relationships);
        $relationship = $metadata->relationships['author'];
        self::assertSame(RelationshipLinkingPolicy::REFERENCE, $relationship->linkingPolicy);
    }

    public function testMultipleRelationshipsWithDifferentPolicies(): void
    {
        $registry = new ResourceRegistry([ArticleWithMixedPolicies::class]);

        $metadata = $registry->getByType('articles');

        self::assertArrayHasKey('author', $metadata->relationships);
        self::assertArrayHasKey('tags', $metadata->relationships);

        $authorRelationship = $metadata->relationships['author'];
        $tagsRelationship = $metadata->relationships['tags'];

        self::assertSame(RelationshipLinkingPolicy::VERIFY, $authorRelationship->linkingPolicy);
        self::assertSame(RelationshipLinkingPolicy::REFERENCE, $tagsRelationship->linkingPolicy);
    }
}

// Test fixtures

#[JsonApiResource(type: 'articles')]
final class ArticleWithDefaultPolicy
{
    #[Relationship(toMany: false, targetType: 'authors')]
    public ?AuthorFixture $author = null;
}

#[JsonApiResource(type: 'articles')]
final class ArticleWithVerifyPolicyEnum
{
    #[Relationship(toMany: false, targetType: 'authors', linkingPolicy: RelationshipLinkingPolicy::VERIFY)]
    public ?AuthorFixture $author = null;
}

#[JsonApiResource(type: 'articles')]
final class ArticleWithVerifyPolicyString
{
    #[Relationship(toMany: false, targetType: 'authors', linkingPolicy: 'verify')]
    public ?AuthorFixture $author = null;
}

#[JsonApiResource(type: 'articles')]
final class ArticleWithReferencePolicyEnum
{
    #[Relationship(toMany: false, targetType: 'authors', linkingPolicy: RelationshipLinkingPolicy::REFERENCE)]
    public ?AuthorFixture $author = null;
}

#[JsonApiResource(type: 'articles')]
final class ArticleWithReferencePolicyString
{
    #[Relationship(toMany: false, targetType: 'authors', linkingPolicy: 'reference')]
    public ?AuthorFixture $author = null;
}

#[JsonApiResource(type: 'articles')]
final class ArticleWithMixedPolicies
{
    #[Relationship(toMany: false, targetType: 'authors', linkingPolicy: RelationshipLinkingPolicy::VERIFY)]
    public ?AuthorFixture $author = null;

    /** @var list<TagFixture> */
    #[Relationship(toMany: true, targetType: 'tags', linkingPolicy: RelationshipLinkingPolicy::REFERENCE)]
    public array $tags = [];
}

#[JsonApiResource(type: 'authors')]
final class AuthorFixture
{
}

#[JsonApiResource(type: 'tags')]
final class TagFixture
{
}
