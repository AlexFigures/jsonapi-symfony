<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Unit\Regression\Fixtures;

#[\AlexFigures\JsonApi\Profile\Attribute\SoftDeletable(deletedByField: 'removedBy')]
final class SoftOwner
{
    public string $removedBy = 'editor@example.test';
}
