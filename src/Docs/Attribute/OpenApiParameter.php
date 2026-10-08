<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Docs\Attribute;

use Attribute;

/**
 * Defines a parameter for an OpenAPI endpoint.
 *
 * @api This attribute is part of the public API and follows semantic versioning.
 * @since 1.0.0
 */
#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
final readonly class OpenApiParameter
{
    /**
     * @param string                    $name        Parameter name
     * @param string                    $in          Parameter location: 'query', 'path', 'header', 'cookie'
     * @param string                    $description Parameter description
     * @param bool                      $required    Whether the parameter is required
     * @param string                    $type        Parameter type: 'string', 'integer', 'boolean', 'array', 'object'
     * @param string|null               $format      Parameter format (e.g., 'date-time', 'email', 'uri')
     * @param array<string, mixed>|null $schema      Full schema definition (overrides type/format if provided)
     * @param mixed                     $example     Example value
     */
    public function __construct(
        public string $name,
        public string $in,
        public string $description = '',
        public bool $required = false,
        public string $type = 'string',
        public ?string $format = null,
        public ?array $schema = null,
        public mixed $example = null,
    ) {
        if (!in_array($in, ['query', 'path', 'header', 'cookie'], true)) {
            throw new \InvalidArgumentException(
                sprintf('Parameter location must be one of: query, path, header, cookie. Got: %s', $in)
            );
        }
    }
}
