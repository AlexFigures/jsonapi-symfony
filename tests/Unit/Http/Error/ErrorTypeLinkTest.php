<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Unit\Http\Error;

use AlexFigures\JsonApi\Http\Error\AtomicErrorRebaser;
use AlexFigures\JsonApi\Http\Error\ErrorBuilder;
use AlexFigures\JsonApi\Http\Exception\BadRequestException;
use PHPUnit\Framework\TestCase;

final class ErrorTypeLinkTest extends TestCase
{
    public function testLinksArePreservedAcrossImmutableEnrichmentAndAtomicRebasing(): void
    {
        $builder = new ErrorBuilder(true);
        $original = $builder->fromPointer('422', 'validation-error', null, 'Invalid input.', '/data', aboutLink: '/errors/occurrence', typeLink: '/problems/validation');
        $enriched = $original->withId('trace-id')->withMergedMeta(['trace' => 'recorded'])->withAboutLink('/errors/updated');
        $exception = AtomicErrorRebaser::rebase(new BadRequestException('Invalid.', [$enriched]), '/atomic:operations/2');
        $serialized = $exception->getErrors()[0]->toArray();
        self::assertSame(['about' => '/errors/updated', 'type' => '/problems/validation'], $serialized['links']);
        self::assertSame('/atomic:operations/2/data', $serialized['source']['pointer']);
        self::assertSame('trace-id', $serialized['id']);
        self::assertSame(['trace' => 'recorded'], $serialized['meta']);
        self::assertSame('/errors/occurrence', $original->aboutLink);
        self::assertSame('/problems/validation', $original->typeLink);
    }

    public function testTypeLinkCanBeAddedReplacedAndRemovedWithoutChangingAbout(): void
    {
        $original = (new ErrorBuilder(false))->create('400', 'error', aboutLink: '/errors/1');
        $linked = $original->withTypeLink('/problems/invalid');
        self::assertSame(['about' => '/errors/1', 'type' => '/problems/invalid'], $linked->toArray()['links']);
        self::assertSame($linked, $linked->withTypeLink('/problems/invalid'));
        self::assertSame('/problems/updated', $linked->withTypeLink('/problems/updated')->typeLink);
        self::assertSame(['about' => '/errors/1'], $linked->withTypeLink(null)->toArray()['links']);
        self::assertArrayNotHasKey('links', $linked->withTypeLink(null)->withAboutLink(null)->toArray());
        self::assertSame(['about' => '/errors/1'], $original->toArray()['links']);
    }

    public function testSourceBuildersForwardTypeLinkAndDoNotInventAbout(): void
    {
        $builder = new ErrorBuilder(true);
        foreach ([
            $builder->fromParameter('400', 'error', null, null, 'filter', typeLink: '/problems/filter'),
            $builder->fromHeader('400', 'error', null, null, 'Accept', typeLink: '/problems/filter'),
        ] as $error) {
            self::assertSame(['type' => '/problems/filter'], $error->toArray()['links']);
        }
        self::assertArrayNotHasKey('links', $builder->create('400', 'error')->toArray());
    }
}
