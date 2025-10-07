<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Filter\Operator;

use Doctrine\DBAL\Platforms\AbstractPlatform;

final class GreaterThanOperator extends AbstractOperator
{
    public function name(): string
    {
        return 'gt';
    }

    public function compile(
        string $rootAlias,
        string $dqlField,
        array $values,
        AbstractPlatform $platform,
    ): DoctrineExpression {
        if ($values === []) {
            throw new \InvalidArgumentException('GreaterThanOperator requires at least one value.');
        }

        $paramName = 'gt_' . uniqid('', true);

        return new DoctrineExpression(
            sprintf('%s > :%s', $dqlField, $paramName),
            [$paramName => $values[0]]
        );
    }
}
