<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Filter\Operator;

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

        return new DoctrineExpression(
            sprintf('%s NOT IN (:%s)', $dqlField, $paramName),
            [$paramName => $values],
        );
    }
}
