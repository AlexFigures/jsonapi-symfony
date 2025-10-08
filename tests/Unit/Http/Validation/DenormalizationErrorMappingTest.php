<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Http\Validation;

use JsonApi\Symfony\Http\Error\ErrorBuilder;
use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Exception\ValidationException;
use JsonApi\Symfony\Http\Validation\ConstraintViolationMapper;
use JsonApi\Symfony\Resource\Metadata\AttributeMetadata;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
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
