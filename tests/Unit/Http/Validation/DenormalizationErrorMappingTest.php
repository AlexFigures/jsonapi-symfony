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
}
