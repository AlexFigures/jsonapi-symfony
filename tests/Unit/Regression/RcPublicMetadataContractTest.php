<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Unit\Regression;

use AlexFigures\JsonApi\Contract\Resource\ResourceMetadataInterface;
use AlexFigures\JsonApi\Resource\Metadata\RelationshipLinkingPolicy;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistry;
use AlexFigures\JsonApi\Tests\Unit\Regression\Fixtures\OtherProjectedResource;
use AlexFigures\JsonApi\Tests\Unit\Regression\Fixtures\PolicyResource;
use AlexFigures\JsonApi\Tests\Unit\Regression\Fixtures\PrimaryResource;
use AlexFigures\JsonApi\Tests\Unit\Regression\Fixtures\ProjectedResource;
use PHPUnit\Framework\TestCase;

final class RcPublicMetadataContractTest extends TestCase
{
    public function testPrimaryResourceWinsOverProjectionAliasesInEitherOrder(): void
    {
        foreach ([[PrimaryResource::class, ProjectedResource::class], [ProjectedResource::class, PrimaryResource::class]] as $classes) {
            $registry = new ResourceRegistry($classes);
            self::assertSame('primary', $registry->getByClass(PrimaryResource::class)?->type);
            self::assertSame('projected', $registry->getByClass(ProjectedResource::class)?->type);
            self::assertInstanceOf(ResourceMetadataInterface::class, $registry->getByType('primary'));
            self::assertSame('primary', $registry->getByType('primary')->getType());
        }
    }

    public function testAmbiguousPersistenceAliasIsRejectedWithoutPrimaryResource(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Ambiguous resource class');
        new ResourceRegistry([ProjectedResource::class, OtherProjectedResource::class]);
    }

    public function testResourceRelationshipPolicyIsDefaultAndExplicitPolicyWins(): void
    {
        $metadata = (new ResourceRegistry([PolicyResource::class]))->getByType('policies');
        self::assertSame(RelationshipLinkingPolicy::VERIFY, $metadata->relationships['inherited']->linkingPolicy);
        self::assertSame(RelationshipLinkingPolicy::REFERENCE, $metadata->relationships['explicit']->linkingPolicy);
    }

    public function testExtensionDtosArePublic(): void
    {
        foreach ([\AlexFigures\JsonApi\Query\Fetch\RelationshipReadMap::class, \AlexFigures\JsonApi\Resource\Metadata\CustomRouteMetadata::class, \AlexFigures\JsonApi\Bridge\Doctrine\Query\DoctrineCollectionQueryProviderInterface::class] as $class) {
            $comment = (new \ReflectionClass($class))->getDocComment();
            self::assertStringNotContainsString('@internal', (string) $comment);
            self::assertStringContainsString('@api', (string) $comment);
        }
    }
}
