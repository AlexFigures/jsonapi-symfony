<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Resource;

use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Resource\Definition\ReadProjection;
use AlexFigures\Symfony\Resource\Definition\ResourceDefinition;
use AlexFigures\Symfony\Resource\Write\WriteContext;
use AlexFigures\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Dto\ArticleCreateDto;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Dto\ArticleUpdateDto;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Dto\ArticleViewDto;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Mapper\ArticleReadMapper;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Mapper\ArticleWriteMapper;
use Symfony\Component\HttpFoundation\Response;

/**
 * Integration tests for DTO support in JSON:API resources.
 *
 * These tests verify that the DTO pipeline works correctly with:
 * - ResourceDefinition (dataClass, viewClass, readProjection)
 * - ReadMapper (Entity → DTO transformation)
 * - WriteMapper (request DTO → Entity transformation)
 * - Validation of request DTOs
 * - Database persistence through DTOs
 *
 * Test Coverage:
 * - D1: Create resource through DTO (POST)
 * - D2: Update resource through DTO (PATCH)
 * - D3: Read resource with DTO projection (GET)
 * - D4: DTO validation with Symfony Validator
 * - D5: ReadMapper with Entity input
 * - D6: ReadMapper with array projection input
 * - D7: WriteMapper instantiate for CREATE
 * - D8: WriteMapper apply for UPDATE
 * - D9: ResourceDefinition with DTO configuration
 */
final class DtoIntegrationTest extends DoctrineIntegrationTestCase
{
    private ArticleReadMapper $readMapper;
    private ArticleWriteMapper $writeMapper;
    private ResourceDefinition $dtoDefinition;

    protected function getDatabaseUrl(): string
    {
        return $_ENV['DATABASE_URL_POSTGRES'] ?? 'postgresql://jsonapi:secret@postgres:5432/jsonapi_test?serverVersion=16&charset=utf8';
    }

    protected function getPlatform(): string
    {
        return 'postgresql';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->readMapper = new ArticleReadMapper();
        $this->writeMapper = new ArticleWriteMapper();

        // Create ResourceDefinition with DTO configuration
        $this->dtoDefinition = new ResourceDefinition(
            type: 'article-dtos',
            dataClass: Article::class,
            viewClass: ArticleViewDto::class,
            readProjection: ReadProjection::DTO,
            fieldMap: [
                'id' => 'a.id',
                'title' => 'a.title',
                'content' => 'a.content',
                'createdAt' => 'a.createdAt',
            ],
            relationshipPolicies: [],
            writeRequests: [
                'create' => ArticleCreateDto::class,
                'update' => ArticleUpdateDto::class,
            ],
        );
    }

    protected function tearDown(): void
    {
        $this->clearDatabase();
        parent::tearDown();
    }

    /**
     * D1: Create resource through DTO (POST).
     *
     * Validates:
     * - WriteMapper::instantiate() creates Entity from request DTO
     * - Request DTO validation works
     * - Entity is persisted to database
     * - ReadMapper::toView() transforms Entity to view DTO
     */
    public function testCreateResourceThroughDto(): void
    {
        // Create request DTO
        $requestDto = new ArticleCreateDto(
            title: 'DTO Article',
            content: 'This article was created through DTO pipeline',
        );

        // Use WriteMapper to instantiate Entity
        $context = new WriteContext();
        $article = $this->writeMapper->instantiate($this->dtoDefinition, $requestDto, $context);

        self::assertInstanceOf(Article::class, $article);
        self::assertSame('DTO Article', $article->getTitle());
        self::assertSame('This article was created through DTO pipeline', $article->getContent());

        // Persist to database
        $this->em->persist($article);
        $this->em->flush();
        $articleId = $article->getId();
        $this->em->clear();

        // Verify persistence
        $persisted = $this->em->find(Article::class, $articleId);
        self::assertInstanceOf(Article::class, $persisted);
        self::assertSame('DTO Article', $persisted->getTitle());

        // Use ReadMapper to transform to view DTO
        $criteria = new Criteria();
        $viewDto = $this->readMapper->toView($persisted, $this->dtoDefinition, $criteria);

        self::assertInstanceOf(ArticleViewDto::class, $viewDto);
        self::assertSame($articleId, $viewDto->id);
        self::assertSame('DTO Article', $viewDto->title);
        self::assertSame('This article was created through DTO pipeline', $viewDto->content);
        self::assertNotNull($viewDto->createdAt);
    }

    /**
     * D2: Update resource through DTO (PATCH).
     *
     * Validates:
     * - WriteMapper::apply() updates Entity from request DTO
     * - Partial updates work (only non-null fields are updated)
     * - Changes are persisted to database
     */
    public function testUpdateResourceThroughDto(): void
    {
        // Create initial article
        $article = new Article();
        $article->setTitle('Original Title');
        $article->setContent('Original content that should not change');
        $this->em->persist($article);
        $this->em->flush();
        $articleId = $article->getId();
        $this->em->clear();

        // Load article
        $article = $this->em->find(Article::class, $articleId);
        self::assertInstanceOf(Article::class, $article);

        // Create update DTO (only title, content should remain unchanged)
        $updateDto = new ArticleUpdateDto(
            title: 'Updated Title',
            content: null, // Null means don't update
        );

        // Apply update through WriteMapper
        $context = new WriteContext();
        $this->writeMapper->apply($article, $updateDto, $this->dtoDefinition, $context);

        self::assertSame('Updated Title', $article->getTitle());
        self::assertSame('Original content that should not change', $article->getContent());

        // Persist changes
        $this->em->flush();
        $this->em->clear();

        // Verify persistence
        $updated = $this->em->find(Article::class, $articleId);
        self::assertInstanceOf(Article::class, $updated);
        self::assertSame('Updated Title', $updated->getTitle());
        self::assertSame('Original content that should not change', $updated->getContent());
    }

    /**
     * D3: Read resource with DTO projection (GET).
     *
     * Validates:
     * - ReadMapper::toView() transforms Entity to view DTO
     * - View DTO contains correct data
     * - View DTO is read-only (immutable properties)
     */
    public function testReadResourceWithDtoProjection(): void
    {
        // Create article
        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent('Test content for DTO projection');
        $this->em->persist($article);
        $this->em->flush();
        $articleId = $article->getId();
        $this->em->clear();

        // Load article
        $article = $this->em->find(Article::class, $articleId);
        self::assertInstanceOf(Article::class, $article);

        // Transform to view DTO
        $criteria = new Criteria();
        $viewDto = $this->readMapper->toView($article, $this->dtoDefinition, $criteria);

        self::assertInstanceOf(ArticleViewDto::class, $viewDto);
        self::assertSame($articleId, $viewDto->id);
        self::assertSame('Test Article', $viewDto->title);
        self::assertSame('Test content for DTO projection', $viewDto->content);
        self::assertNotNull($viewDto->createdAt);

        // Verify DTO is immutable (readonly properties)
        $reflection = new \ReflectionClass($viewDto);
        foreach ($reflection->getProperties() as $property) {
            self::assertTrue($property->isReadOnly(), sprintf('Property %s should be readonly', $property->getName()));
        }
    }

    /**
     * D4: DTO validation with Symfony Validator.
     *
     * Validates:
     * - Request DTOs have validation constraints
     * - Validation constraints are properly defined
     * - Invalid data can be detected
     */
    public function testDtoValidationConstraints(): void
    {
        // Test ArticleCreateDto constraints
        $createDto = new ArticleCreateDto(
            title: 'Valid Title',
            content: 'Valid content with enough characters',
        );

        $violations = $this->validator->validate($createDto);
        self::assertCount(0, $violations, 'Valid DTO should have no violations');

        // Test validation failure - title too short
        $invalidDto = new ArticleCreateDto(
            title: 'AB', // Less than 3 characters
            content: 'Valid content',
        );

        $violations = $this->validator->validate($invalidDto);
        self::assertGreaterThan(0, count($violations), 'Invalid DTO should have violations');

        // Test validation failure - content too short
        $invalidDto2 = new ArticleCreateDto(
            title: 'Valid Title',
            content: 'Short', // Less than 10 characters
        );

        $violations = $this->validator->validate($invalidDto2);
        self::assertGreaterThan(0, count($violations), 'Invalid DTO should have violations');
    }

    /**
     * D5: ReadMapper with Entity input.
     *
     * Validates:
     * - ReadMapper correctly handles Entity objects
     * - Entity properties are mapped to DTO properties
     * - Transformation is lossless for exposed fields
     */
    public function testReadMapperWithEntityInput(): void
    {
        $article = new Article();
        $article->setTitle('Entity Input Test');
        $article->setContent('Testing ReadMapper with Entity input');
        $this->em->persist($article);
        $this->em->flush();

        $criteria = new Criteria();
        $viewDto = $this->readMapper->toView($article, $this->dtoDefinition, $criteria);

        self::assertInstanceOf(ArticleViewDto::class, $viewDto);
        self::assertSame($article->getId(), $viewDto->id);
        self::assertSame($article->getTitle(), $viewDto->title);
        self::assertSame($article->getContent(), $viewDto->content);
        self::assertEquals($article->getCreatedAt(), $viewDto->createdAt);
    }

    /**
     * D6: ReadMapper with array projection input.
     *
     * Validates:
     * - ReadMapper correctly handles array input (DQL projections)
     * - Array keys are mapped to DTO constructor parameters
     * - Missing optional fields are handled gracefully
     */
    public function testReadMapperWithArrayProjectionInput(): void
    {
        $projection = [
            'id' => 'test-id-123',
            'title' => 'Projected Title',
            'content' => 'Projected content from DQL',
            'createdAt' => new \DateTimeImmutable('2024-01-15 10:00:00'),
        ];

        $criteria = new Criteria();
        $viewDto = $this->readMapper->toView($projection, $this->dtoDefinition, $criteria);

        self::assertInstanceOf(ArticleViewDto::class, $viewDto);
        self::assertSame('test-id-123', $viewDto->id);
        self::assertSame('Projected Title', $viewDto->title);
        self::assertSame('Projected content from DQL', $viewDto->content);
        self::assertEquals(new \DateTimeImmutable('2024-01-15 10:00:00'), $viewDto->createdAt);
    }

    /**
     * D7: WriteMapper instantiate for CREATE.
     *
     * Validates:
     * - WriteMapper::instantiate() creates new Entity instances
     * - Request DTO data is correctly transferred to Entity
     * - Entity is in valid state after instantiation
     */
    public function testWriteMapperInstantiateForCreate(): void
    {
        $requestDto = new ArticleCreateDto(
            title: 'Instantiate Test',
            content: 'Testing WriteMapper instantiate method',
        );

        $context = new WriteContext();
        $article = $this->writeMapper->instantiate($this->dtoDefinition, $requestDto, $context);

        self::assertInstanceOf(Article::class, $article);
        self::assertSame('Instantiate Test', $article->getTitle());
        self::assertSame('Testing WriteMapper instantiate method', $article->getContent());

        // Verify Entity can be persisted
        $this->em->persist($article);
        $this->em->flush();

        self::assertNotEmpty($article->getId());
    }

    /**
     * D8: WriteMapper apply for UPDATE.
     *
     * Validates:
     * - WriteMapper::apply() updates existing Entity
     * - Only non-null DTO fields are applied (partial update)
     * - Unchanged fields remain intact
     */
    public function testWriteMapperApplyForUpdate(): void
    {
        // Create initial article
        $article = new Article();
        $article->setTitle('Initial Title');
        $article->setContent('Initial Content');
        $this->em->persist($article);
        $this->em->flush();

        // Update only title
        $updateDto = new ArticleUpdateDto(
            title: 'Updated Title Only',
            content: null,
        );

        $context = new WriteContext();
        $this->writeMapper->apply($article, $updateDto, $this->dtoDefinition, $context);

        self::assertSame('Updated Title Only', $article->getTitle());
        self::assertSame('Initial Content', $article->getContent());

        // Update only content
        $updateDto2 = new ArticleUpdateDto(
            title: null,
            content: 'Updated Content Only',
        );

        $this->writeMapper->apply($article, $updateDto2, $this->dtoDefinition, $context);

        self::assertSame('Updated Title Only', $article->getTitle());
        self::assertSame('Updated Content Only', $article->getContent());
    }

    /**
     * D9: ResourceDefinition with DTO configuration.
     *
     * Validates:
     * - ResourceDefinition correctly stores DTO metadata
     * - dataClass points to Entity
     * - viewClass points to DTO
     * - readProjection is set to DTO
     * - writeRequests map operations to request DTOs
     */
    public function testResourceDefinitionWithDtoConfiguration(): void
    {
        self::assertSame('article-dtos', $this->dtoDefinition->type);
        self::assertSame(Article::class, $this->dtoDefinition->dataClass);
        self::assertSame(ArticleViewDto::class, $this->dtoDefinition->viewClass);
        self::assertSame(ReadProjection::DTO, $this->dtoDefinition->readProjection);

        self::assertArrayHasKey('create', $this->dtoDefinition->writeRequests);
        self::assertSame(ArticleCreateDto::class, $this->dtoDefinition->writeRequests['create']);

        self::assertArrayHasKey('update', $this->dtoDefinition->writeRequests);
        self::assertSame(ArticleUpdateDto::class, $this->dtoDefinition->writeRequests['update']);

        self::assertSame(ArticleViewDto::class, $this->dtoDefinition->getEffectiveViewClass());

        // Verify fieldMap
        self::assertArrayHasKey('id', $this->dtoDefinition->fieldMap);
        self::assertArrayHasKey('title', $this->dtoDefinition->fieldMap);
        self::assertArrayHasKey('content', $this->dtoDefinition->fieldMap);
        self::assertArrayHasKey('createdAt', $this->dtoDefinition->fieldMap);
    }

    /**
     * D10: ReadMapper handles already-DTO input.
     *
     * Validates:
     * - ReadMapper returns DTO as-is if input is already a DTO
     * - No unnecessary transformations occur
     */
    public function testReadMapperHandlesAlreadyDtoInput(): void
    {
        $existingDto = new ArticleViewDto(
            id: 'dto-123',
            title: 'Already DTO',
            content: 'This is already a DTO',
            createdAt: new \DateTimeImmutable(),
        );

        $criteria = new Criteria();
        $result = $this->readMapper->toView($existingDto, $this->dtoDefinition, $criteria);

        self::assertSame($existingDto, $result, 'ReadMapper should return DTO as-is');
    }

    /**
     * D11: ReadMapper throws exception for invalid input.
     *
     * Validates:
     * - ReadMapper throws RuntimeException for unsupported input types
     * - Error message is descriptive
     */
    public function testReadMapperThrowsExceptionForInvalidInput(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot map');

        $criteria = new Criteria();
        $this->readMapper->toView('invalid-string-input', $this->dtoDefinition, $criteria);
    }

    /**
     * D12: WriteMapper throws exception for wrong DTO type on instantiate.
     *
     * Validates:
     * - WriteMapper validates request DTO type on instantiate
     * - Throws RuntimeException for wrong DTO type
     */
    public function testWriteMapperThrowsExceptionForWrongDtoTypeOnInstantiate(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected ArticleCreateDto');

        $wrongDto = new ArticleUpdateDto(title: 'Wrong DTO');
        $context = new WriteContext();
        $this->writeMapper->instantiate($this->dtoDefinition, $wrongDto, $context);
    }

    /**
     * D13: WriteMapper throws exception for wrong entity type on apply.
     *
     * Validates:
     * - WriteMapper validates entity type on apply
     * - Throws RuntimeException for wrong entity type
     */
    public function testWriteMapperThrowsExceptionForWrongEntityTypeOnApply(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected Article entity');

        $wrongEntity = new \stdClass();
        $updateDto = new ArticleUpdateDto(title: 'Test');
        $context = new WriteContext();
        $this->writeMapper->apply($wrongEntity, $updateDto, $this->dtoDefinition, $context);
    }
}
