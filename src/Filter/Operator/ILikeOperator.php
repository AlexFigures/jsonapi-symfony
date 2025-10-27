<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Filter\Operator;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Case-insensitive LIKE operator.
 *
 * Uses the custom ILIKE() DQL function which translates to:
 * - Native ILIKE on PostgreSQL
 * - LOWER() LIKE LOWER() on other databases
 *
 * Example:
 * ```
 * GET /api/products?filter[name][ilike]=laptop
 * ```
 *
 * @api This operator is part of the public API and follows semantic versioning.
 * @since 0.4.0
 */
final class ILikeOperator extends AbstractOperator
{
    public function name(): string
    {
        return 'ilike';
    }

    public function compile(
        string $rootAlias,
        string $dqlField,
        array $values,
        AbstractPlatform $platform,
    ): DoctrineExpression {
        if ($values === []) {
            throw new \InvalidArgumentException('ILikeOperator requires at least one value.');
        }

        $paramName = 'ilike_' . str_replace('.', '_', uniqid('', true));

        $value = $values[0];
        if (!is_scalar($value) && !$value instanceof \Stringable) {
            throw new \InvalidArgumentException('ILikeOperator requires string-compatible value.');
        }

        // Add wildcards for partial matching
        $pattern = '%' . (string) $value . '%';

        // Use custom ILIKE() DQL function
        // This function is registered by the bundle and translates to native ILIKE on PostgreSQL
        // or LOWER() LIKE LOWER() on other databases
        return new DoctrineExpression(
            sprintf('ILIKE(%s, :%s) = true', $dqlField, $paramName),
            [$paramName => $pattern]
        );
    }
}
