<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Profile;

use AlexFigures\Symfony\Profile\Attribute\Auditable;
use AlexFigures\Symfony\Profile\Attribute\SoftDeletable;
use AlexFigures\Symfony\Profile\AttributeReader;
use AlexFigures\Symfony\Profile\Builtin\AuditTrailProfile;
use AlexFigures\Symfony\Profile\Builtin\RelationshipCountsProfile;
use AlexFigures\Symfony\Profile\Builtin\SoftDeleteProfile;
use AlexFigures\Symfony\Profile\Validation\ProfileValidator;
use AlexFigures\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\AuditableProduct;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\SoftDeletableArticle;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Integration tests for profile validation with real entities and database.
 *
 * Tests that ProfileValidator correctly validates profile requirements
 * against real Doctrine entities with proper attributes.
 */
#[CoversClass(ProfileValidator::class)]
#[CoversClass(AttributeReader::class)]
final class ProfileValidationIntegrationTest extends DoctrineIntegrationTestCase
{
    private ProfileValidator $profileValidator;
    private AttributeReader $attributeReader;

    protected function getDatabaseUrl(): string
    {
        $url = $_ENV['DATABASE_URL_PGSQL'] ?? 'postgresql://jsonapi:jsonapi@postgres:5432/jsonapi_test?serverVersion=16&charset=utf8';
        assert(is_string($url));
        return $url;
    }

    protected function getPlatform(): string
    {
        return 'postgresql';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->attributeReader = new AttributeReader();
        $this->profileValidator = new ProfileValidator($this->em, $this->attributeReader);
    }

    public function testAttributeReaderCanReadSoftDeletableAttribute(): void
    {
        $attribute = $this->attributeReader->getAttribute(
            SoftDeletableArticle::class,
            SoftDeletable::class
        );

        self::assertInstanceOf(SoftDeletable::class, $attribute);
        self::assertSame('deletedAt', $attribute->deletedAtField);
        self::assertSame('deletedBy', $attribute->deletedByField);
    }

    public function testAttributeReaderCanReadAuditableAttribute(): void
    {
        $attribute = $this->attributeReader->getAttribute(
            AuditableProduct::class,
            Auditable::class
        );

        self::assertInstanceOf(Auditable::class, $attribute);
        self::assertSame('createdAt', $attribute->createdAtField);
        self::assertSame('updatedAt', $attribute->updatedAtField);
        self::assertSame('createdBy', $attribute->createdByField);
        self::assertSame('updatedBy', $attribute->updatedByField);
    }

    public function testAttributeReaderReturnsNullForMissingAttribute(): void
    {
        $attribute = $this->attributeReader->getAttribute(
            Article::class,
            SoftDeletable::class
        );

        self::assertNull($attribute);
    }

    public function testValidationPassesForEntityWithCorrectSoftDeletableFields(): void
    {
        $profile = new SoftDeleteProfile();
        $profilesByUri = [$profile->uri() => $profile];
        $resourceTypes = ['soft-deletable-articles' => SoftDeletableArticle::class];
        $enabledProfiles = ['soft-deletable-articles' => [$profile->uri()]];

        $result = $this->profileValidator->validate($profilesByUri, $resourceTypes, $enabledProfiles);

        self::assertTrue($result->isValid(), 'Validation should pass for entity with correct fields');
        self::assertFalse($result->hasErrors(), 'Should have no errors');
        self::assertCount(0, $result->getErrors());
    }

    public function testValidationPassesForEntityWithCorrectAuditableFields(): void
    {
        $profile = new AuditTrailProfile();
        $profilesByUri = [$profile->uri() => $profile];
        $resourceTypes = ['auditable-products' => AuditableProduct::class];
        $enabledProfiles = ['auditable-products' => [$profile->uri()]];

        $result = $this->profileValidator->validate($profilesByUri, $resourceTypes, $enabledProfiles);

        self::assertTrue($result->isValid(), 'Validation should pass for entity with correct fields');
        self::assertFalse($result->hasErrors(), 'Should have no errors');
        self::assertCount(0, $result->getErrors());
    }

    public function testValidationFailsForEntityWithoutRequiredAttribute(): void
    {
        $profile = new SoftDeleteProfile();
        $profilesByUri = [$profile->uri() => $profile];
        $resourceTypes = ['articles' => Article::class]; // Article doesn't have #[SoftDeletable]
        $enabledProfiles = ['articles' => [$profile->uri()]];

        $result = $this->profileValidator->validate($profilesByUri, $resourceTypes, $enabledProfiles);

        self::assertFalse($result->isValid(), 'Validation should fail for entity without required attribute');
        self::assertTrue($result->hasErrors(), 'Should have errors');
        self::assertGreaterThan(0, $result->getErrorCount());

        $errors = $result->getErrors();
        $errorMessages = array_map(fn ($e) => $e->message, $errors);
        $hasAttributeError = false;
        foreach ($errorMessages as $message) {
            if (str_contains($message, 'must have #[SoftDeletable] attribute')) {
                $hasAttributeError = true;
                break;
            }
        }
        self::assertTrue($hasAttributeError, 'Should have error about missing attribute');
    }

    public function testValidationPassesForProfileWithoutRequirements(): void
    {
        $profile = new RelationshipCountsProfile();
        $profilesByUri = [$profile->uri() => $profile];
        $resourceTypes = ['articles' => Article::class];
        $enabledProfiles = ['articles' => [$profile->uri()]];

        $result = $this->profileValidator->validate($profilesByUri, $resourceTypes, $enabledProfiles);

        self::assertTrue($result->isValid(), 'Validation should pass for profile without requirements');
        self::assertFalse($result->hasErrors(), 'Should have no errors');
    }

    public function testValidationWithMultipleProfiles(): void
    {
        $softDeleteProfile = new SoftDeleteProfile();
        $relationshipCountsProfile = new RelationshipCountsProfile();

        $profilesByUri = [
            $softDeleteProfile->uri() => $softDeleteProfile,
            $relationshipCountsProfile->uri() => $relationshipCountsProfile,
        ];

        $resourceTypes = ['soft-deletable-articles' => SoftDeletableArticle::class];
        $enabledProfiles = [
            'soft-deletable-articles' => [
                $softDeleteProfile->uri(),
                $relationshipCountsProfile->uri(),
            ],
        ];

        $result = $this->profileValidator->validate($profilesByUri, $resourceTypes, $enabledProfiles);

        self::assertTrue($result->isValid(), 'Validation should pass when all profiles are satisfied');
        self::assertFalse($result->hasErrors(), 'Should have no errors');
    }

    public function testValidationWithMultipleResourceTypes(): void
    {
        $softDeleteProfile = new SoftDeleteProfile();
        $auditTrailProfile = new AuditTrailProfile();

        $profilesByUri = [
            $softDeleteProfile->uri() => $softDeleteProfile,
            $auditTrailProfile->uri() => $auditTrailProfile,
        ];

        $resourceTypes = [
            'soft-deletable-articles' => SoftDeletableArticle::class,
            'auditable-products' => AuditableProduct::class,
        ];

        $enabledProfiles = [
            'soft-deletable-articles' => [$softDeleteProfile->uri()],
            'auditable-products' => [$auditTrailProfile->uri()],
        ];

        $result = $this->profileValidator->validate($profilesByUri, $resourceTypes, $enabledProfiles);

        self::assertTrue($result->isValid(), 'Validation should pass for multiple resource types');
        self::assertFalse($result->hasErrors(), 'Should have no errors');
    }

    public function testValidationReportsAllErrorsForInvalidEntity(): void
    {
        $softDeleteProfile = new SoftDeleteProfile();
        $auditTrailProfile = new AuditTrailProfile();

        $profilesByUri = [
            $softDeleteProfile->uri() => $softDeleteProfile,
            $auditTrailProfile->uri() => $auditTrailProfile,
        ];

        $resourceTypes = ['articles' => Article::class]; // Missing both attributes

        $enabledProfiles = [
            'articles' => [
                $softDeleteProfile->uri(),
                $auditTrailProfile->uri(),
            ],
        ];

        $result = $this->profileValidator->validate($profilesByUri, $resourceTypes, $enabledProfiles);

        self::assertFalse($result->isValid(), 'Validation should fail');
        self::assertTrue($result->hasErrors(), 'Should have errors');
        self::assertGreaterThanOrEqual(2, $result->getErrorCount(), 'Should have errors from both profiles');
    }

    public function testValidationResultFormatting(): void
    {
        $profile = new SoftDeleteProfile();
        $profilesByUri = [$profile->uri() => $profile];
        $resourceTypes = ['articles' => Article::class];
        $enabledProfiles = ['articles' => [$profile->uri()]];

        $result = $this->profileValidator->validate($profilesByUri, $resourceTypes, $enabledProfiles);

        $formattedErrors = $result->formatErrors();
        self::assertNotEmpty($formattedErrors);

        foreach ($formattedErrors as $error) {
            self::assertStringContainsString('articles', $error);
        }

        $summary = $result->getSummary();
        self::assertStringContainsString('error', strtolower($summary));
    }
}
