<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Filter\Compiler\Doctrine;

use AlexFigures\Symfony\Filter\Ast\Comparison;
use AlexFigures\Symfony\Filter\Ast\Conjunction;
use AlexFigures\Symfony\Filter\Ast\Disjunction;
use AlexFigures\Symfony\Filter\Ast\Node;
use AlexFigures\Symfony\Filter\Handler\Registry\FilterHandlerRegistry;
use AlexFigures\Symfony\Filter\Operator\Registry;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\QueryBuilder;

/**
 * Compiles filter ASTs into Doctrine ORM QueryBuilder expressions.
 */
final class DoctrineFilterCompiler
{
    public function __construct(
        private readonly Registry $operators,
        private readonly FilterHandlerRegistry $filterHandlers,
    ) {
    }

    public function apply(QueryBuilder $qb, Node $ast, AbstractPlatform $platform): void
    {
        $rootAliases = $qb->getRootAliases();
        if ($rootAliases === []) {
            throw new \LogicException('QueryBuilder must have at least one root alias.');
        }

        $rootAlias = $rootAliases[0];
        $expression = $this->compileNode($ast, $rootAlias, $platform);

        if ($expression !== null) {
            $qb->andWhere($expression->dql);

            foreach ($expression->parameters as $name => $value) {
                $qb->setParameter($name, $value);
            }
        }
    }

    /**
     * Recursively compile AST node into DQL expression.
     */
    private function compileNode(Node $node, string $rootAlias, AbstractPlatform $platform): ?\AlexFigures\Symfony\Filter\Operator\DoctrineExpression
    {
        if ($node instanceof Comparison) {
            return $this->compileComparison($node, $rootAlias, $platform);
        }

        if ($node instanceof Conjunction) {
            return $this->compileConjunction($node, $rootAlias, $platform);
        }

        if ($node instanceof Disjunction) {
            return $this->compileDisjunction($node, $rootAlias, $platform);
        }

        throw new \InvalidArgumentException(sprintf('Unsupported AST node type: %s', get_class($node)));
    }

    private function compileComparison(Comparison $node, string $rootAlias, AbstractPlatform $platform): \AlexFigures\Symfony\Filter\Operator\DoctrineExpression
    {
        // Check for custom filter handler first
        $customHandler = $this->filterHandlers->findHandler($node->fieldPath, $node->operator);
        if ($customHandler !== null) {
            // For custom handlers, we need to create a temporary QueryBuilder to capture the modifications
            // This is a simplified approach - in a real implementation, you might want to return
            // a special expression type that can be applied later
            throw new \LogicException('Custom filter handlers are not yet fully implemented in DoctrineFilterCompiler. Please use the repository-level integration instead.');
        }

        $operator = $this->operators->get($node->operator);

        if ($operator === null) {
            throw new \InvalidArgumentException(sprintf('Unknown filter operator: %s', $node->operator));
        }

        // Build DQL field path (e.g., "e.name" or "e.author.name")
        $dqlField = $this->buildDqlFieldPath($rootAlias, $node->fieldPath);

        return $operator->compile($rootAlias, $dqlField, $node->values, $platform);
    }

    private function compileConjunction(Conjunction $node, string $rootAlias, AbstractPlatform $platform): ?\AlexFigures\Symfony\Filter\Operator\DoctrineExpression
    {
        if ($node->children === []) {
            return null;
        }

        $expressions = [];
        $allParameters = [];

        foreach ($node->children as $child) {
            $expr = $this->compileNode($child, $rootAlias, $platform);
            if ($expr !== null) {
                $expressions[] = $expr->dql;
                $allParameters = array_merge($allParameters, $expr->parameters);
            }
        }

        if ($expressions === []) {
            return null;
        }

        $dql = '(' . implode(' AND ', $expressions) . ')';

        return new \AlexFigures\Symfony\Filter\Operator\DoctrineExpression($dql, $allParameters);
    }

    private function compileDisjunction(Disjunction $node, string $rootAlias, AbstractPlatform $platform): ?\AlexFigures\Symfony\Filter\Operator\DoctrineExpression
    {
        if ($node->children === []) {
            return null;
        }

        $expressions = [];
        $allParameters = [];

        foreach ($node->children as $child) {
            $expr = $this->compileNode($child, $rootAlias, $platform);
            if ($expr !== null) {
                $expressions[] = $expr->dql;
                $allParameters = array_merge($allParameters, $expr->parameters);
            }
        }

        if ($expressions === []) {
            return null;
        }

        $dql = '(' . implode(' OR ', $expressions) . ')';

        return new \AlexFigures\Symfony\Filter\Operator\DoctrineExpression($dql, $allParameters);
    }

    /**
     * Build DQL field path from root alias and field path.
     *
     * Examples:
     * - "name" -> "e.name"
     * - "author.name" -> "e.author.name" (will need JOIN in repository)
     */
    private function buildDqlFieldPath(string $rootAlias, string $fieldPath): string
    {
        return $rootAlias . '.' . $fieldPath;
    }
}
