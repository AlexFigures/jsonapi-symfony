<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Filter\Parser;

use AlexFigures\Symfony\Filter\Ast\Between;
use AlexFigures\Symfony\Filter\Ast\Comparison;
use AlexFigures\Symfony\Filter\Ast\Conjunction;
use AlexFigures\Symfony\Filter\Ast\Disjunction;
use AlexFigures\Symfony\Filter\Ast\Node;
use AlexFigures\Symfony\Filter\Ast\NullCheck;

/**
 * Heuristic filter parser responsible for turning query parameters into an AST.
 *
 * A dedicated grammar will arrive alongside the full Stage 5 implementation.
 * Until then the parser recognises a pragmatic subset of the JSON:API filter
 * dialect so consumers can begin exercising the downstream components.
 */
final readonly class FilterParser
{
    public function __construct(private int $maxDepth = 8)
    {
    }
    /**
     * @param array<array-key, mixed> $rawFilters
     */
    public function parse(array $rawFilters): ?Node
    {
        return $this->parseGroup($rawFilters);
    }

    /**
     * @param array<array-key, mixed> $raw
     */
    private function parseGroup(array $raw, int $depth = 0): ?Node
    {
        if ($depth > $this->maxDepth) {
            throw new \InvalidArgumentException('Maximum filter depth exceeded.');
        }
        $nodes = [];

        foreach ($raw as $key => $value) {
            if ($key === 'and') {
                $node = $this->parseLogicalGroup($value, true, $depth + 1);
            } elseif ($key === 'or') {
                $node = $this->parseLogicalGroup($value, false, $depth + 1);
            } elseif (is_string($key)) {
                $node = $this->parseFieldComparisons($key, $value);
            } else {
                throw new \InvalidArgumentException(sprintf('Invalid filter key type "%s".', get_debug_type($key)));
            }

            if ($node !== null) {
                $nodes[] = $node;
            }
        }

        if ($nodes === []) {
            return null;
        }

        if (count($nodes) === 1) {
            return $nodes[0];
        }

        return new Conjunction($nodes);
    }

    private function parseLogicalGroup(mixed $raw, bool $isAnd, int $depth): ?Node
    {
        if (!is_array($raw)) {
            throw new \InvalidArgumentException(sprintf('Logical group must be an array, "%s" given.', get_debug_type($raw)));
        }

        if (!$this->isList($raw)) {
            throw new \InvalidArgumentException('Logical groups must be provided as a list of filter objects.');
        }

        $children = [];

        foreach ($raw as $index => $childRaw) {
            if (!is_array($childRaw)) {
                throw new \InvalidArgumentException(sprintf('Logical group entry at index %s must be an array, "%s" given.', (string) $index, get_debug_type($childRaw)));
            }

            $node = $this->parseGroup($childRaw, $depth);

            if ($node !== null) {
                $children[] = $node;
            }
        }

        if ($children === []) {
            return null;
        }

        return $isAnd ? new Conjunction($children) : new Disjunction($children);
    }

    private function parseFieldComparisons(string $fieldPath, mixed $raw): ?Node
    {
        $field = (string) new FieldPath($fieldPath);

        $nodes = [];

        if ($raw === null) {
            $nodes[] = new NullCheck($field, true);
        } elseif (!is_array($raw)) {
            $nodes[] = new Comparison($field, 'eq', [$raw]);
        } elseif ($raw === []) {
            return null;
        } elseif ($this->isList($raw)) {
            $nodes[] = new Comparison($field, 'in', array_values($raw));
        } else {
            foreach ($raw as $operator => $value) {
                if (!is_string($operator)) {
                    throw new \InvalidArgumentException('Filter operators must be identified by strings.');
                }

                $nodes = array_merge($nodes, $this->parseOperator($field, $operator, $value));
            }
        }

        if ($nodes === []) {
            return null;
        }

        if (count($nodes) === 1) {
            return $nodes[0];
        }

        return new Conjunction($nodes);
    }

    /**
     * @return list<Node>
     */
    private function parseOperator(string $field, string $operator, mixed $value): array
    {
        switch ($operator) {
            case 'eq':
            case 'ne':
            case 'neq':
            case 'lt':
            case 'lte':
            case 'gt':
            case 'gte':
            case 'like':
            case 'ilike':
                $values = $this->normalizeValues($value);
                if (count($values) !== 1) {
                    throw new \InvalidArgumentException('Comparison operators require exactly one operand.');
                }
                return [new Comparison($field, $operator === 'ne' ? 'neq' : $operator, $values)];
            case 'in':
            case 'nin':
                $values = $value === '' ? [] : $this->normalizeValues($value);



                return [new Comparison($field, $operator, $values)];
            case 'between':
                if (!is_array($value)) {
                    throw new \InvalidArgumentException('The "between" operator expects an array.');
                }

                if ($this->isAssoc($value)) {
                    if (count($value) !== 2 || !array_key_exists('from', $value) || !array_key_exists('to', $value)) {
                        throw new \InvalidArgumentException('The "between" operator expects "from" and "to" keys.');
                    }

                    $this->normalizeValues(array_values($value));
                    return [new Between($field, $value['from'], $value['to'])];
                }

                $values = array_values($value);

                if (count($values) !== 2) {
                    throw new \InvalidArgumentException('The "between" operator expects exactly two values.');
                }

                $this->normalizeValues($values);
                return [new Between($field, $values[0], $values[1])];
            case 'isnull':
                if (!is_scalar($value) || !in_array(strtolower((string) $value), ['true', 'false', '1', '0', 'yes', 'no', ''], true)) {
                    throw new \InvalidArgumentException('isnull expects a boolean operand.');
                }
                return [new NullCheck($field, $this->toBool($value))];
        }

        throw new \InvalidArgumentException(sprintf('Unsupported operator "%s".', $operator));
    }

    /**
     * @return list<mixed>
     */
    private function normalizeValues(mixed $raw): array
    {
        if ($raw instanceof \Traversable) {
            $raw = iterator_to_array($raw, false);
        }

        if (is_array($raw) && !array_is_list($raw)) {
            throw new \InvalidArgumentException('Filter operands must be scalars or lists of scalars.');
        }
        $values = is_array($raw) ? array_values($raw) : [$raw];
        foreach ($values as $value) {
            if (!is_scalar($value) && $value !== null) {
                throw new \InvalidArgumentException('Filter operands must be scalar values.');
            }
        }
        return $values;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower($value);

            return in_array($normalized, ['1', 'true', 'yes'], true);
        }

        return (bool) $value;
    }

    /**
     * @param array<mixed> $value
     */
    private function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    /**
     * @param array<mixed> $value
     */
    private function isAssoc(array $value): bool
    {
        return !$this->isList($value);
    }
}
