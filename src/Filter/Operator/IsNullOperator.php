<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Filter\Operator;

use Doctrine\DBAL\Platforms\AbstractPlatform;

final class IsNullOperator extends AbstractOperator
{
    public function name(): string
    {
        return 'isnull';
    }

    public function compile(
        string $rootAlias,
        string $dqlField,
        array $values,
        AbstractPlatform $platform,
    ): DoctrineExpression {
        // IsNull operator doesn't need values, but if provided, use first value as boolean
        $isNull = true;
        if ($values !== [] && is_bool($values[0])) {
            $isNull = $values[0];
        }

        $condition = $isNull ? 'IS NULL' : 'IS NOT NULL';

        return new DoctrineExpression(
            sprintf('%s %s', $dqlField, $condition),
            []
        );
    }
}
