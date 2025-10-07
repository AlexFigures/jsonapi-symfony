<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Filter\Operator;

use Doctrine\DBAL\Platforms\AbstractPlatform;

final class BetweenOperator extends AbstractOperator
{
    public function name(): string
    {
        return 'between';
    }

    public function compile(
        string $rootAlias,
        string $dqlField,
        array $values,
        AbstractPlatform $platform,
    ): DoctrineExpression {
        if (count($values) < 2) {
            throw new \InvalidArgumentException('BetweenOperator requires exactly two values (min and max).');
        }

        $paramMin = 'between_min_' . uniqid('', true);
        $paramMax = 'between_max_' . uniqid('', true);

        return new DoctrineExpression(
            sprintf('%s BETWEEN :%s AND :%s', $dqlField, $paramMin, $paramMax),
            [$paramMin => $values[0], $paramMax => $values[1]]
        );
    }
}
