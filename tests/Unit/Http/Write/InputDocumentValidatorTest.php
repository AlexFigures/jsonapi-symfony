<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Http\Write;

use AlexFigures\Symfony\Http\Error\ErrorBuilder;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Exception\BadRequestException;
use AlexFigures\Symfony\Http\Write\InputDocumentValidator;
use AlexFigures\Symfony\Http\Write\WriteConfig;
use AlexFigures\Symfony\Resource\Metadata\AttributeMetadata;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use PHPUnit\Framework\TestCase;

final class InputDocumentValidatorTest extends TestCase
{
    public function testValidateAllowsAttributesWithoutIsWritable(): void
    {
        $metadata = $this->articleMetadata();
        $validator = $this->createValidator($metadata);

        $payload = [
            'data' => [
                'type' => 'articles',
                'attributes' => [
                    'createdAt' => '2024-01-01T00:00:00Z',
                ],
            ],
        ];

        $result = $validator->validateAndExtract('articles', null, $payload, 'POST');

        self::assertSame('articles', $result['type']);
        self::assertNull($result['id']);
        self::assertSame(['createdAt' => '2024-01-01T00:00:00Z'], $result['attributes']);
    }

    public function testValidateRejectsUnknownAttributes(): void
    {
        $metadata = $this->articleMetadata();
        $validator = $this->createValidator($metadata);

        $payload = [
            'data' => [
                'type' => 'articles',
                'attributes' => [
                    'unknown' => 'value',
                ],
            ],
        ];

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Attributes validation failed.');

        $validator->validateAndExtract('articles', null, $payload, 'POST');
    }

    private function createValidator(ResourceMetadata $metadata): InputDocumentValidator
    {
        $registry = new class ($metadata) implements ResourceRegistryInterface {
            public function __construct(private ResourceMetadata $metadata)
            {
            }

            public function getByType(string $type): ResourceMetadata
            {
                if ($type !== $this->metadata->type) {
                    throw new \LogicException(sprintf('Unknown resource type "%s".', $type));
                }

                return $this->metadata;
            }

            public function hasType(string $type): bool
            {
                return $type === $this->metadata->type;
            }

            public function getByClass(string $class): ?ResourceMetadata
            {
                return $class === $this->metadata->class ? $this->metadata : null;
            }

            public function all(): array
            {
                return [$this->metadata];
            }
        };

        return new InputDocumentValidator(
            $registry,
            new WriteConfig(),
            new ErrorMapper(new ErrorBuilder(false)),
        );
    }

    private function articleMetadata(): ResourceMetadata
    {
        return new ResourceMetadata(
            type: 'articles',
            class: DummyArticle::class,
            attributes: [
                'title' => new AttributeMetadata(
                    name: 'title',
                    propertyPath: 'title',
                    types: ['string'],
                    nullable: false,
                ),
                'createdAt' => new AttributeMetadata(
                    name: 'createdAt',
                    propertyPath: 'createdAt',
                    types: ['string'],
                    nullable: false,
                ),
            ],
            relationships: [],
            normalizationContext: ['groups' => ['article:read']],
            denormalizationContext: ['groups' => ['article:write']],
        );
    }
}

final class DummyArticle
{
}
