<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Filter\Operator;

use Doctrine\DBAL\Platforms\AbstractPlatform;

final class NotInOperator extends AbstractOperator
{
    public function name(): string
    {
        return 'nin';
    }

    public function compile(
        string $rootAlias,
        string $dqlField,
        array $values,
        AbstractPlatform $platform,
    ): DoctrineExpression {
        if ($values === []) {
            throw new \InvalidArgumentException('NotInOperator requires at least one value.');
        }

        $paramName = 'nin_' . str_replace('.', '_', uniqid('', true));

        // Include NULL values in NOT IN logic
        // This ensures that records without the relationship are also returned
        // Example: "tags.id NOT IN [1,2]" should return articles without tags
        return new DoctrineExpression(
            sprintf('(%s NOT IN (:%s) OR %s IS NULL)', $dqlField, $paramName, $dqlField),
            [$paramName => $values],
        );
    }
}
