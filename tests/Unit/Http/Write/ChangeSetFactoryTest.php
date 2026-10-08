<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Unit\Http\Write;

use AlexFigures\JsonApi\Http\Exception\BadRequestException;
use AlexFigures\JsonApi\Http\Write\ChangeSetFactory;
use AlexFigures\JsonApi\Resource\Metadata\AttributeMetadata;
use AlexFigures\JsonApi\Resource\Metadata\ResourceMetadata;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistryInterface;
use PHPUnit\Framework\TestCase;

/**
 */
#[\PHPUnit\Framework\Attributes\Group('unit')]
final class ChangeSetFactoryTest extends TestCase
{
    private ResourceRegistryInterface $registry;
    private ChangeSetFactory $factory;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ResourceRegistryInterface::class);
        $this->factory = new ChangeSetFactory($this->registry);
    }

    public function testFromInputCreatesChangeSetWithAttributesAndRelationships(): void
    {
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: \AlexFigures\JsonApi\Tests\Fixtures\Model\Article::class,
            attributes: [
                'title' => new AttributeMetadata(name: 'title'),
                'body' => new AttributeMetadata(name: 'body'),
            ],
            relationships: []
        );

        $this->registry->method('getByType')
            ->with('articles')
            ->willReturn($metadata);

        $attributes = [
            'title' => 'Test Article',
            'body' => 'Article content',
        ];

        $relationships = [
            'author' => ['data' => ['type' => 'authors', 'id' => '123']],
            'tags' => ['data' => [
                ['type' => 'tags', 'id' => '1'],
                ['type' => 'tags', 'id' => '2'],
            ]],
        ];

        $changeSet = $this->factory->fromInput('articles', $attributes, $relationships);

        $this->assertEquals(['title' => 'Test Article', 'body' => 'Article content'], $changeSet->attributes);
        $this->assertEquals($relationships, $changeSet->relationships);
    }

    public function testFromInputWithPropertyPathMapping(): void
    {
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: \AlexFigures\JsonApi\Tests\Fixtures\Model\Article::class,
            attributes: [
                'title' => new AttributeMetadata(name: 'title', propertyPath: 'articleTitle'),
                'body' => new AttributeMetadata(name: 'body'),
            ],
            relationships: []
        );

        $this->registry->method('getByType')
            ->with('articles')
            ->willReturn($metadata);

        $attributes = [
            'title' => 'Test Article',
            'body' => 'Article content',
        ];

        $changeSet = $this->factory->fromInput('articles', $attributes, []);

        // 'title' should be mapped to 'articleTitle' property path
        $this->assertEquals([
            'articleTitle' => 'Test Article',
            'body' => 'Article content',
        ], $changeSet->attributes);
    }

    public function testFromInputWithEmptyRelationships(): void
    {
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: \AlexFigures\JsonApi\Tests\Fixtures\Model\Article::class,
            attributes: [
                'title' => new AttributeMetadata(name: 'title'),
            ],
            relationships: []
        );

        $this->registry->method('getByType')
            ->with('articles')
            ->willReturn($metadata);

        $changeSet = $this->factory->fromInput('articles', ['title' => 'Test']);

        $this->assertEquals(['title' => 'Test'], $changeSet->attributes);
        $this->assertEquals([], $changeSet->relationships);
    }

    public function testFromInputThrowsExceptionForUnknownAttribute(): void
    {
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: \AlexFigures\JsonApi\Tests\Fixtures\Model\Article::class,
            attributes: [
                'title' => new AttributeMetadata(name: 'title'),
            ],
            relationships: []
        );

        $this->registry->method('getByType')
            ->with('articles')
            ->willReturn($metadata);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Unknown attribute "unknown" for type "articles".');

        $this->factory->fromInput('articles', ['title' => 'Test', 'unknown' => 'value'], []);
    }

    public function testFromInputWithOnlyRelationshipsNoAttributes(): void
    {
        $metadata = new ResourceMetadata(
            type: 'articles',
            class: \AlexFigures\JsonApi\Tests\Fixtures\Model\Article::class,
            attributes: [],
            relationships: []
        );

        $this->registry->method('getByType')
            ->with('articles')
            ->willReturn($metadata);

        $relationships = [
            'author' => ['data' => ['type' => 'authors', 'id' => '123']],
        ];

        $changeSet = $this->factory->fromInput('articles', [], $relationships);

        $this->assertEquals([], $changeSet->attributes);
        $this->assertEquals($relationships, $changeSet->relationships);
    }
}
