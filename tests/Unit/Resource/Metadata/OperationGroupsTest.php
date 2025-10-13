<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Resource\Metadata;

use AlexFigures\Symfony\Resource\Metadata\OperationGroups;
use PHPUnit\Framework\TestCase;

final class OperationGroupsTest extends TestCase
{
    public function testDefaultGroups(): void
    {
        $groups = OperationGroups::default();

        $this->assertSame(['create', 'Default'], $groups->getValidationGroups(true));
        $this->assertSame(['update', 'Default'], $groups->getValidationGroups(false));
        $this->assertSame(['write', 'create', 'Default'], $groups->getSerializationGroups(true));
        $this->assertSame(['write', 'update', 'Default'], $groups->getSerializationGroups(false));
    }

    public function testCustomValidationGroups(): void
    {
        $groups = OperationGroups::withValidationGroups(
            ['custom_create', 'Default'],
            ['custom_update', 'Default']
        );

        $this->assertSame(['custom_create', 'Default'], $groups->getValidationGroups(true));
        $this->assertSame(['custom_update', 'Default'], $groups->getValidationGroups(false));

        // Serialization groups should remain default
        $this->assertSame(['write', 'create', 'Default'], $groups->getSerializationGroups(true));
        $this->assertSame(['write', 'update', 'Default'], $groups->getSerializationGroups(false));
    }

    public function testCustomSerializationGroups(): void
    {
        $groups = OperationGroups::withSerializationGroups(
            ['custom_write', 'custom_create'],
            ['custom_write', 'custom_update']
        );

        $this->assertSame(['custom_write', 'custom_create'], $groups->getSerializationGroups(true));
        $this->assertSame(['custom_write', 'custom_update'], $groups->getSerializationGroups(false));

        // Validation groups should remain default
        $this->assertSame(['create', 'Default'], $groups->getValidationGroups(true));
        $this->assertSame(['update', 'Default'], $groups->getValidationGroups(false));
    }

    public function testFullyCustomGroups(): void
    {
        $groups = new OperationGroups(
            validationGroupsCreate: ['strict_create'],
            validationGroupsUpdate: ['strict_update'],
            serializationGroupsCreate: ['api_create'],
            serializationGroupsUpdate: ['api_update']
        );

        $this->assertSame(['strict_create'], $groups->getValidationGroups(true));
        $this->assertSame(['strict_update'], $groups->getValidationGroups(false));
        $this->assertSame(['api_create'], $groups->getSerializationGroups(true));
        $this->assertSame(['api_update'], $groups->getSerializationGroups(false));
    }

    public function testEmptyGroups(): void
    {
        $groups = new OperationGroups(
            validationGroupsCreate: [],
            validationGroupsUpdate: [],
            serializationGroupsCreate: [],
            serializationGroupsUpdate: []
        );

        $this->assertSame([], $groups->getValidationGroups(true));
        $this->assertSame([], $groups->getValidationGroups(false));
        $this->assertSame([], $groups->getSerializationGroups(true));
        $this->assertSame([], $groups->getSerializationGroups(false));
    }
}
