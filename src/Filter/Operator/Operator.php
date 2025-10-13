<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Filter\Operator;

use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Contract implemented by filter operators.
 */
interface Operator
{
    public function name(): string;

    public function supportsField(ResourceMetadata $meta, string $fieldPath): bool;

    /**
     * Normalise the raw value(s) provided by the client.
     *
     * @return list<mixed>
     */
    public function normalizeValues(mixed $raw): array;

    /**
     * Compile a comparison node into a Doctrine expression fragment.
     */
    /**
     * @param list<mixed> $values
     */
    public function compile(
        string $rootAlias,
        string $dqlField,
        array $values,
        AbstractPlatform $platform,
    ): DoctrineExpression;
}

/**
 * Lightweight value object carrying the compiled DQL and bound parameters.
 */
final class DoctrineExpression
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        public readonly string $dql,
        public readonly array $parameters,
    ) {
    }
}
