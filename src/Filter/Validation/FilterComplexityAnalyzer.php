<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Filter\Validation;

use AlexFigures\Symfony\Filter\Ast\Between;
use AlexFigures\Symfony\Filter\Ast\Comparison;
use AlexFigures\Symfony\Filter\Ast\Conjunction;
use AlexFigures\Symfony\Filter\Ast\Disjunction;
use AlexFigures\Symfony\Filter\Ast\Group;
use AlexFigures\Symfony\Filter\Ast\Node;
use AlexFigures\Symfony\Filter\Ast\NullCheck;

/** Counts every structural node, including logical groups, without recursive traversal. */
final class FilterComplexityAnalyzer
{
    public function analyze(?Node $root): FilterComplexity
    {
        $depth = $nodes = $operands = $hops = 0;
        $pending = $root === null ? [] : [[$root, 1]];
        while ($pending !== []) {
            [$node, $level] = array_pop($pending);
            ++$nodes;
            $depth = max($depth, $level);
            if ($node instanceof Conjunction || $node instanceof Disjunction) {
                foreach ($node->children as $child) {
                    $pending[] = [$child, $level + 1];
                }
            } elseif ($node instanceof Group) {
                $pending[] = [$node->expression, $level + 1];
            } elseif ($node instanceof Comparison || $node instanceof Between || $node instanceof NullCheck) {
                $operands += match (true) {
                    $node instanceof Comparison => count($node->values),
                    $node instanceof Between => 2,
                    default => 0,
                };
                $hops += substr_count($node->fieldPath, '.');
            } else {
                throw new \InvalidArgumentException('Unsupported filter AST node: ' . $node::class);
            }
        }

        return new FilterComplexity($depth, $nodes, $operands, $hops);
    }
}
