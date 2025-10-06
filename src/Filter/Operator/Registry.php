<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Filter\Operator;

/**
 * Simple in-memory operator registry.
 */
final class Registry
{
    /** @var array<string, Operator> */
    private array $operators = [];

    public function register(Operator $operator): void
    {
        $this->operators[$operator->name()] = $operator;
    }

    public function has(string $name): bool
    {
        return isset($this->operators[$name]);
    }

    public function get(string $name): Operator
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException(sprintf('Unknown operator "%s".', $name));
        }

        return $this->operators[$name];
    }

    /**
     * @return list<Operator>
     */
    public function all(): array
    {
        return array_values($this->operators);
    }
}
