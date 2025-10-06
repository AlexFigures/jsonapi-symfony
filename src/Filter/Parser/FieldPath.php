<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Filter\Parser;

/**
 * Represents a dotted field path like "author.name".
 */
final class FieldPath
{
    /** @var list<string> */
    private array $segments;

    public function __construct(string $path)
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('Field path cannot be empty.');
        }

        $this->segments = explode('.', $trimmed);
    }

    /**
     * @return list<string>
     */
    public function segments(): array
    {
        return $this->segments;
    }

    public function __toString(): string
    {
        return implode('.', $this->segments);
    }
}
