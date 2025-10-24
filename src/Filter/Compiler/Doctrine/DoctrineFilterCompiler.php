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
    /** @var array<string, string> */
    private array $joinedForFilter = [];

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

        // Reset joined relationships for this query
        $this->joinedForFilter = [];

        // Create JOINs for relationship paths in the filter
        $this->createJoinsForFilter($qb, $ast, $rootAlias);

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

    private function compileComparison(Comparison $node, string $rootAlias, AbstractPlatform $platform): ?\AlexFigures\Symfony\Filter\Operator\DoctrineExpression
    {
        // Check for custom filter handler first
        $customHandler = $this->filterHandlers->findHandler($node->fieldPath, $node->operator);
        if ($customHandler !== null) {
            // Custom handlers are applied at the repository level (GenericDoctrineRepository)
            // Skip them here and return null to exclude from the compiled expression
            // The repository will apply them separately using the handler's apply() method
            return null;
        }

        $operator = $this->operators->get($node->operator);

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
     * - "author.id" -> "filter_author.id" (uses JOIN alias)
     * - "tags.id" -> "filter_tags.id" (uses JOIN alias)
     */
    private function buildDqlFieldPath(string $rootAlias, string $fieldPath): string
    {
        // Check if this is a relationship field path (e.g., "author.id")
        if (str_contains($fieldPath, '.')) {
            $segments = explode('.', $fieldPath);
            $fieldName = array_pop($segments); // Last segment is the actual field

            // Build the full join path to find the alias
            $currentAlias = $rootAlias;
            foreach ($segments as $relationshipName) {
                $fullJoinPath = $currentAlias . '.' . $relationshipName;

                // Get the alias for this join (should have been created in createJoinsForFilter)
                if (isset($this->joinedForFilter[$fullJoinPath])) {
                    $currentAlias = $this->joinedForFilter[$fullJoinPath];
                } else {
                    // Fallback: use the relationship name as alias
                    $currentAlias = 'filter_' . $relationshipName;
                }
            }

            return $currentAlias . '.' . $fieldName;
        }

        // Direct field on the root entity
        return $rootAlias . '.' . $fieldPath;
    }

    /**
     * Create JOINs for all relationship paths in the filter AST.
     */
    private function createJoinsForFilter(QueryBuilder $qb, Node $ast, string $rootAlias): void
    {
        $this->collectRelationshipPaths($ast, $qb, $rootAlias);
    }

    /**
     * Recursively collect relationship paths from the AST and create JOINs.
     */
    private function collectRelationshipPaths(Node $node, QueryBuilder $qb, string $rootAlias): void
    {
        if ($node instanceof Comparison) {
            $this->createJoinForFieldPath($qb, $rootAlias, $node->fieldPath);
        } elseif ($node instanceof Conjunction || $node instanceof Disjunction) {
            foreach ($node->children as $child) {
                $this->collectRelationshipPaths($child, $qb, $rootAlias);
            }
        }
    }

    /**
     * Create JOIN for a field path if it contains relationships.
     */
    private function createJoinForFieldPath(QueryBuilder $qb, string $rootAlias, string $fieldPath): void
    {
        // Check if this is a relationship field path (e.g., "author.id")
        if (!str_contains($fieldPath, '.')) {
            return; // Direct field, no JOIN needed
        }

        $segments = explode('.', $fieldPath);
        array_pop($segments); // Remove the field name, keep only relationship path

        // Build JOINs for each segment
        $currentAlias = $rootAlias;
        foreach ($segments as $index => $relationshipName) {
            $fullJoinPath = $currentAlias . '.' . $relationshipName;
            $joinAlias = 'filter_' . str_replace('.', '_', implode('_', array_slice($segments, 0, $index + 1)));

            // Create JOIN if not already created
            if (!isset($this->joinedForFilter[$fullJoinPath])) {
                $qb->leftJoin($fullJoinPath, $joinAlias);
                $this->joinedForFilter[$fullJoinPath] = $joinAlias;
            }

            $currentAlias = $joinAlias;
        }
    }
}
