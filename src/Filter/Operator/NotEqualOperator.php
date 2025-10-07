<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Filter\Operator;

use Doctrine\DBAL\Platforms\AbstractPlatform;

final class NotEqualOperator extends AbstractOperator
{
    public function name(): string
    {
        return 'neq';
    }

    public function compile(
        string $rootAlias,
        string $dqlField,
        array $values,
        AbstractPlatform $platform,
    ): DoctrineExpression {
        if ($values === []) {
            throw new \InvalidArgumentException('NotEqualOperator requires at least one value.');
        }

        $paramName = 'ne_' . uniqid('', true);

        return new DoctrineExpression(
            sprintf('%s != :%s', $dqlField, $paramName),
            [$paramName => $values[0]]
        );
    }
}
