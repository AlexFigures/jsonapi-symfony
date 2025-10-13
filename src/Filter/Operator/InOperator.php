<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Filter\Operator;

use Doctrine\DBAL\Platforms\AbstractPlatform;

final class InOperator extends AbstractOperator
{
    public function name(): string
    {
        return 'in';
    }

    public function compile(
        string $rootAlias,
        string $dqlField,
        array $values,
        AbstractPlatform $platform,
    ): DoctrineExpression {
        if ($values === []) {
            throw new \InvalidArgumentException('InOperator requires at least one value.');
        }

        $paramName = 'in_' . str_replace('.', '_', uniqid('', true));

        return new DoctrineExpression(
            sprintf('%s IN (:%s)', $dqlField, $paramName),
            [$paramName => $values]
        );
    }
}
