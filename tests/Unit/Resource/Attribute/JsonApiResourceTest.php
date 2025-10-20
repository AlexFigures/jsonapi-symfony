<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Resource\Attribute;

use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use PHPUnit\Framework\TestCase;

final class JsonApiResourceTest extends TestCase
{
    public function testNormalizationGroupsAreFiltered(): void
    {
        $attribute = new JsonApiResource(
            type: 'articles',
            normalizationContext: ['groups' => ['articles:read', 123, '', null]],
        );

        self::assertSame(['articles:read'], $attribute->getNormalizationGroups());
    }

    public function testDenormalizationGroupsAreFiltered(): void
    {
        $attribute = new JsonApiResource(
            type: 'articles',
            denormalizationContext: ['groups' => ['articles:write', 'articles:write', 456]],
        );

        self::assertSame(['articles:write'], $attribute->getDenormalizationGroups());
    }

    public function testNonArrayGroupsReturnEmptyList(): void
    {
        $attribute = new JsonApiResource(
            type: 'articles',
            normalizationContext: ['groups' => 'articles:read'],
            denormalizationContext: ['groups' => 'articles:write'],
        );

        self::assertSame([], $attribute->getNormalizationGroups());
        self::assertSame([], $attribute->getDenormalizationGroups());
    }
}
