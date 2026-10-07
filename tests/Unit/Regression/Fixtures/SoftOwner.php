<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Regression\Fixtures;

#[\AlexFigures\Symfony\Profile\Attribute\SoftDeletable(deletedByField: 'removedBy')]
final class SoftOwner
{
    public string $removedBy = 'editor@example.test';
}
