<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Filter\Operator;

use Doctrine\DBAL\Platforms\AbstractPlatform;

final class LikeOperator extends AbstractOperator
{
    public function name(): string
    {
        return 'like';
    }

    public function compile(
        string $rootAlias,
        string $dqlField,
        array $values,
        AbstractPlatform $platform,
    ): DoctrineExpression {
        if ($values === []) {
            throw new \InvalidArgumentException('LikeOperator requires at least one value.');
        }

        $paramName = 'like_' . str_replace('.', '_', uniqid('', true));

        // Add wildcards for partial matching
        $pattern = '%' . $values[0] . '%';

        return new DoctrineExpression(
            sprintf('%s LIKE :%s', $dqlField, $paramName),
            [$paramName => $pattern]
        );
    }
}
