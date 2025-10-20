<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Resource\Metadata;

use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use PHPUnit\Framework\TestCase;

final class ResourceMetadataGroupsTest extends TestCase
{
    public function testGetNormalizationGroups(): void
    {
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: ArticleStub::class,
            attributes: [],
            relationships: [],
            normalizationContext: ['groups' => ['article:read', 'common']],
        );

        $this->assertSame(['article:read', 'common'], $metadata->getNormalizationGroups());
    }

    public function testGetNormalizationGroupsEmpty(): void
    {
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: ArticleStub::class,
            attributes: [],
            relationships: [],
        );

        $this->assertSame([], $metadata->getNormalizationGroups());
    }

    public function testGetDenormalizationGroups(): void
    {
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: ArticleStub::class,
            attributes: [],
            relationships: [],
            denormalizationContext: ['groups' => ['article:write']],
        );

        // Should include 'Default' group automatically
        $this->assertSame(['article:write', 'Default'], $metadata->getDenormalizationGroups());
    }

    public function testGetDenormalizationGroupsWithDefault(): void
    {
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: ArticleStub::class,
            attributes: [],
            relationships: [],
            denormalizationContext: ['groups' => ['article:write', 'Default']],
        );

        // Should not duplicate 'Default' group
        $this->assertSame(['article:write', 'Default'], $metadata->getDenormalizationGroups());
    }

    public function testGetDenormalizationGroupsEmpty(): void
    {
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: ArticleStub::class,
            attributes: [],
            relationships: [],
        );

        // Should still include 'Default' group
        $this->assertSame(['Default'], $metadata->getDenormalizationGroups());
    }

    public function testContextsAreIndependent(): void
    {
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: ArticleStub::class,
            attributes: [],
            relationships: [],
            normalizationContext: ['groups' => ['article:read']],
            denormalizationContext: ['groups' => ['article:write']],
        );

        $this->assertSame(['article:read'], $metadata->getNormalizationGroups());
        $this->assertSame(['article:write', 'Default'], $metadata->getDenormalizationGroups());
    }

    public function testNormalizationGroupsFiltersInvalidValues(): void
    {
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: ArticleStub::class,
            attributes: [],
            relationships: [],
            normalizationContext: ['groups' => ['article:read', 123, '', null]],
        );

        $this->assertSame(['article:read'], $metadata->getNormalizationGroups());
    }

    public function testDenormalizationGroupsFiltersInvalidValuesAndAddsDefault(): void
    {
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: ArticleStub::class,
            attributes: [],
            relationships: [],
            denormalizationContext: ['groups' => ['article:write', 'article:write', 123]],
        );

        $this->assertSame(['article:write', 'Default'], $metadata->getDenormalizationGroups());
    }

    public function testNonArrayGroupsAreIgnored(): void
    {
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: ArticleStub::class,
            attributes: [],
            relationships: [],
            normalizationContext: ['groups' => 'article:read'],
            denormalizationContext: ['groups' => 'article:write'],
        );

        $this->assertSame([], $metadata->getNormalizationGroups());
        $this->assertSame(['Default'], $metadata->getDenormalizationGroups());
    }
}

final class ArticleStub
{
}
