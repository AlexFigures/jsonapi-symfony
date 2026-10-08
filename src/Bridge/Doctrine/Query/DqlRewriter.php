<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Bridge\Doctrine\Query;

use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\TokenType;

/** @internal Rename identifiers and parameters without touching literals or field names. */
final class DqlRewriter
{
    /** @param array<string, string> $aliases
     * @param array<string, string> $parameters
     */
    public static function rewrite(string $dql, array $aliases = [], array $parameters = []): string
    {
        $lexer = new Lexer($dql);
        $previous = null;
        $edits = [];
        while ($lexer->moveNext()) {
            $token = $lexer->lookahead;
            $replacement = null;
            if ($token->type === TokenType::T_INPUT_PARAMETER) {
                $replacement = isset($parameters[substr($token->value, 1)]) ? ':' . $parameters[substr($token->value, 1)] : null;
            } elseif ($token->type === TokenType::T_IDENTIFIER && $previous !== TokenType::T_DOT) {
                $replacement = $aliases[$token->value] ?? null;
            }
            if ($replacement !== null) {
                $edits[] = [$token->position, strlen($token->value), $replacement];
            }
            $previous = $token->type;
        }
        foreach (array_reverse($edits) as [$position, $length, $replacement]) {
            $dql = substr_replace($dql, $replacement, $position, $length);
        }
        return $dql;
    }
}
