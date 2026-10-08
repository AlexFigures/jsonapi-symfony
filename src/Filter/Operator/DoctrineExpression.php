<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Filter\Operator;

/**
 * Lightweight value object carrying the compiled DQL and bound parameters.
 * @api
 */
final readonly class DoctrineExpression
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        public string $dql,
        public array $parameters,
    ) {
    }
}
