<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Regression;

use AlexFigures\Symfony\Bridge\Symfony\Locator\RelationshipReaderLocator;
use AlexFigures\Symfony\Bridge\Symfony\Locator\RelationshipUpdaterLocator;
use AlexFigures\Symfony\Contract\Data\RelationshipReader;
use AlexFigures\Symfony\Contract\Data\RelationshipUpdater;
use AlexFigures\Symfony\Contract\Data\ResourceIdentifier;
use AlexFigures\Symfony\Contract\Data\Slice;
use AlexFigures\Symfony\Contract\Data\SliceIds;
use AlexFigures\Symfony\Contract\Data\TypedRelationshipReader;
use AlexFigures\Symfony\Contract\Data\TypedRelationshipUpdater;
use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Query\Pagination;
use PHPUnit\Framework\TestCase;

final class TypedRelationshipDispatchTest extends TestCase
{
    public function testReadsDispatchOnSourceTypeAndPreserveQueryObjects(): void
    {
        $first = $this->createMock(TypedRelationshipReader::class);
        $second = $this->createMock(TypedRelationshipReader::class);
        $fallback = $this->createMock(RelationshipReader::class);
        $first->method('supports')->willReturnCallback(static fn (string $type): bool => $type === 'first');
        $second->method('supports')->willReturnCallback(static fn (string $type): bool => $type === 'second');
        $locator = new RelationshipReaderLocator([$first, $second], $fallback);
        foreach (['first' => $first, 'second' => $second, 'other' => $fallback] as $type => $reader) {
            $pagination = new Pagination(2, 5);
            $criteria = new Criteria($pagination);
            $model = new \stdClass();
            $ids = new SliceIds(['target'], 2, 5, 6);
            $slice = new Slice([$model], 2, 5, 6);
            $reader->expects(self::once())->method('getToOneId')->with($type, 'owner', 'relation')->willReturn('target');
            $reader->expects(self::once())->method('getToManyIds')->with($type, 'owner', 'relation', self::identicalTo($pagination))->willReturn($ids);
            $reader->expects(self::once())->method('getRelatedResource')->with($type, 'owner', 'relation')->willReturn($model);
            $reader->expects(self::once())->method('getRelatedCollection')->with($type, 'owner', 'relation', self::identicalTo($criteria))->willReturn($slice);
            self::assertSame('target', $locator->getToOneId($type, 'owner', 'relation'));
            self::assertSame($ids, $locator->getToManyIds($type, 'owner', 'relation', $pagination));
            self::assertSame($model, $locator->getRelatedResource($type, 'owner', 'relation'));
            self::assertSame($slice, $locator->getRelatedCollection($type, 'owner', 'relation', $criteria));
        }
    }

    public function testAllMutationsDispatchToTypedUpdaterOrFallback(): void
    {
        $typed = $this->createMock(TypedRelationshipUpdater::class);
        $fallback = $this->createMock(RelationshipUpdater::class);
        $typed->method('supports')->willReturnCallback(static fn (string $type): bool => $type === 'first');
        $locator = new RelationshipUpdaterLocator([$typed], $fallback);
        foreach (['first' => $typed, 'other' => $fallback] as $type => $updater) {
            $target = new ResourceIdentifier('targets', 'target');
            $updater->expects(self::exactly(2))->method('replaceToOne')->with($type, 'owner', 'relation', self::logicalOr(self::identicalTo($target), self::isNull()));
            foreach (['replaceToMany', 'addToMany', 'removeFromToMany'] as $method) {
                $updater->expects(self::once())->method($method)->with($type, 'owner', 'relation', [$target]);
                $locator->$method($type, 'owner', 'relation', [$target]);
            }
            $locator->replaceToOne($type, 'owner', 'relation', $target);
            $locator->replaceToOne($type, 'owner', 'relation', null);
        }
    }
}
