<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Filter\Operator;

use Doctrine\DBAL\Platforms\AbstractPlatform;

final class GreaterOrEqualOperator extends AbstractOperator
{
    public function name(): string
    {
        return 'gte';
    }

    public function compile(
        string $rootAlias,
        string $dqlField,
        array $values,
        AbstractPlatform $platform,
    ): DoctrineExpression {
        if ($values === []) {
            throw new \InvalidArgumentException('GreaterOrEqualOperator requires at least one value.');
        }

        $paramName = 'gte_' . uniqid('', true);

        return new DoctrineExpression(
            sprintf('%s >= :%s', $dqlField, $paramName),
            [$paramName => $values[0]]
        );
    }
}
