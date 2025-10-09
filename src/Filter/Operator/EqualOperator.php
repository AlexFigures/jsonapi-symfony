<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Filter\Operator;

use Doctrine\DBAL\Platforms\AbstractPlatform;

final class EqualOperator extends AbstractOperator
{
    public function name(): string
    {
        return 'eq';
    }

    public function compile(
        string $rootAlias,
        string $dqlField,
        array $values,
        AbstractPlatform $platform,
    ): DoctrineExpression {
        if ($values === []) {
            throw new \InvalidArgumentException('EqualOperator requires at least one value.');
        }

        $paramName = 'eq_' . str_replace('.', '_', uniqid('', true));

        return new DoctrineExpression(
            sprintf('%s = :%s', $dqlField, $paramName),
            [$paramName => $values[0]]
        );
    }
}
