<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Doctrine\DQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * Custom DQL function for case-insensitive LIKE comparison.
 *
 * Syntax: ILIKE(field, pattern)
 *
 * On PostgreSQL, translates to: field ILIKE pattern
 * On other databases, translates to: LOWER(field) LIKE LOWER(pattern)
 *
 * Example DQL:
 * ```
 * SELECT e FROM Entity e WHERE ILIKE(e.name, :pattern) = true
 * ```
 *
 * This is automatically registered by the bundle and can be overridden
 * in your Doctrine configuration if needed.
 *
 * @internal This class is used internally by the bundle and may change without notice.
 */
final class ILikeFunction extends FunctionNode
{
    private Node|string $field;
    private Node|string $pattern;

    /**
     * Parse the DQL function arguments.
     *
     * Expected syntax: ILIKE(field, pattern)
     */
    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->field = $parser->ArithmeticPrimary();

        $parser->match(TokenType::T_COMMA);

        $this->pattern = $parser->ArithmeticPrimary();

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    /**
     * Generate SQL for the function.
     *
     * Uses native ILIKE on PostgreSQL, LOWER() on other databases.
     */
    public function getSql(SqlWalker $sqlWalker): string
    {
        $platform = $sqlWalker->getConnection()->getDatabasePlatform();
        $platformName = $platform->getName();

        // Dispatch nodes to SQL, or use string directly
        $field = is_string($this->field) ? $this->field : $this->field->dispatch($sqlWalker);
        $pattern = is_string($this->pattern) ? $this->pattern : $this->pattern->dispatch($sqlWalker);

        // PostgreSQL supports ILIKE natively
        if ($platformName === 'postgresql') {
            return sprintf('(%s ILIKE %s)', $field, $pattern);
        }

        // For other databases, use LOWER() for case-insensitive comparison
        return sprintf('(LOWER(%s) LIKE LOWER(%s))', $field, $pattern);
    }
}
