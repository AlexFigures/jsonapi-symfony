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
        throw new \LogicException('BetweenOperator compilation is not implemented yet.');
    }
}
