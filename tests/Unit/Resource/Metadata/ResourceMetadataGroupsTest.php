<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Resource\Metadata;

use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use PHPUnit\Framework\TestCase;

final class ResourceMetadataGroupsTest extends TestCase
{
    public function testGetNormalizationGroups(): void
    {
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: 'App\Entity\Article',
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
            class: 'App\Entity\Article',
            attributes: [],
            relationships: [],
        );

        $this->assertSame([], $metadata->getNormalizationGroups());
    }

    public function testGetDenormalizationGroups(): void
    {
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: 'App\Entity\Article',
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
            class: 'App\Entity\Article',
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
            class: 'App\Entity\Article',
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
            class: 'App\Entity\Article',
            attributes: [],
            relationships: [],
            normalizationContext: ['groups' => ['article:read']],
            denormalizationContext: ['groups' => ['article:write']],
        );

        $this->assertSame(['article:read'], $metadata->getNormalizationGroups());
        $this->assertSame(['article:write', 'Default'], $metadata->getDenormalizationGroups());
    }
}

