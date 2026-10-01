<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Http\Validation;

use AlexFigures\Symfony\Http\Error\ErrorBuilder;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Exception\ValidationException;
use AlexFigures\Symfony\Http\Validation\ConstraintViolationMapper;
use AlexFigures\Symfony\Resource\Metadata\AttributeMetadata;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

final class DenormalizationErrorMappingTest extends TestCase
{
    private ConstraintViolationMapper $mapper;
    private ResourceRegistryInterface $registry;
    private ErrorMapper $errorMapper;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ResourceRegistryInterface::class);
        $errorBuilder = new ErrorBuilder(true);
        $this->errorMapper = new ErrorMapper($errorBuilder);
        $this->mapper = new ConstraintViolationMapper($this->registry, $this->errorMapper);
    }

    public function testMapUnknownDenormalizationException(): void
    {
        $exception = new \RuntimeException('Unknown error occurred');

        $result = $this->mapper->mapDenormErrors('articles', $exception);

        $this->assertInstanceOf(ValidationException::class, $result);
        $this->assertCount(1, $result->getErrors());

        $error = $result->getErrors()[0];
        $this->assertSame('422', $error->status);
        $this->assertSame('validation-error', $error->code);
        $this->assertStringContainsString('Unknown error occurred', $error->detail);
        $this->assertSame('/data', $error->source?->pointer);
    }

    public function testMapDenormErrorsMethodExists(): void
    {
        $this->assertTrue(method_exists($this->mapper, 'mapDenormErrors'));
    }

    public function testWrappedEnumValueErrorPreservesValueAndPointer(): void
    {
        $this->registry->method('getByType')->with('articles')->willReturn(new ResourceMetadata(
            type: 'articles',
            class: \stdClass::class,
            attributes: ['status' => new AttributeMetadata('status', 'status')],
            relationships: [],
        ));
        $exception = NotNormalizableValueException::createForUnexpectedDataType(
            'The data must belong to a backed enumeration.',
            'invalid-status',
            ['int', 'string'],
            'status',
            true,
            previous: new \ValueError('"invalid-status" is not a valid backing value for enum ArticleStatus'),
        );

        $error = $this->mapper->mapDenormErrors('articles', $exception)->getErrors()[0];

        self::assertSame('422', $error->status);
        self::assertStringContainsString('invalid-status', $error->detail);
        self::assertStringContainsString('Expected type: int|string', $error->detail);
        self::assertSame('/data/attributes/status', $error->source?->pointer);
    }
}
