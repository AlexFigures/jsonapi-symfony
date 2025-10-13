<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Filter\Operator;

use Doctrine\DBAL\Platforms\AbstractPlatform;

final class LessThanOperator extends AbstractOperator
{
    public function name(): string
    {
        return 'lt';
    }

    public function compile(
        string $rootAlias,
        string $dqlField,
        array $values,
        AbstractPlatform $platform,
    ): DoctrineExpression {
        if ($values === []) {
            throw new \InvalidArgumentException('LessThanOperator requires at least one value.');
        }

        $paramName = 'lt_' . str_replace('.', '_', uniqid('', true));

        return new DoctrineExpression(
            sprintf('%s < :%s', $dqlField, $paramName),
            [$paramName => $values[0]]
        );
    }
}
