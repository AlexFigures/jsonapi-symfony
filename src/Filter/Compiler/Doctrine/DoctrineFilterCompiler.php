<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Filter\Compiler\Doctrine;

use AlexFigures\JsonApi\Filter\Ast\Comparison;
use AlexFigures\JsonApi\Filter\Ast\Conjunction;
use AlexFigures\JsonApi\Filter\Ast\Disjunction;
use AlexFigures\JsonApi\Filter\Ast\Node;
use AlexFigures\JsonApi\Filter\Handler\Registry\FilterHandlerRegistry;
use AlexFigures\JsonApi\Filter\Operator\Registry;
use AlexFigures\JsonApi\Resource\Metadata\ResourceMetadata;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\QueryBuilder;

/**
 * Compiles filter ASTs into Doctrine ORM QueryBuilder expressions.
 * @internal
 */
final class DoctrineFilterCompiler
{
    /** @var array<string, string> */
    private array $joinedForFilter = [];

    private ?ResourceMetadata $currentMetadata = null;

    private ?QueryBuilder $query = null;

    private int $leaf = 0;

    public function __construct(
        private readonly Registry $operators,
        private readonly FilterHandlerRegistry $filterHandlers,
    ) {
    }

    /** @internal Preserve the repository's registered handler set during compilation. */
    public function withHandlers(FilterHandlerRegistry $handlers): self
    {
        return new self($this->operators, $handlers);
    }

    public function apply(QueryBuilder $qb, Node $ast, AbstractPlatform $platform, ?ResourceMetadata $metadata = null): void
    {
        // Store metadata for use in other methods
        $this->currentMetadata = $metadata;
        $this->query = $qb;
        $this->leaf = 0;

        $rootAliases = $qb->getRootAliases();
        if ($rootAliases === []) {
            throw new \LogicException('QueryBuilder must have at least one root alias.');
        }

        $rootAlias = $rootAliases[0];

        // Reset joined relationships for this query
        $this->joinedForFilter = [];

        try {
            // Create JOINs for relationship paths in the filter
            $this->createJoinsForFilter($qb, $ast, $rootAlias, $metadata);

            $expression = $this->compileNode($ast, $rootAlias, $platform);

            if ($expression !== null) {
                $qb->andWhere($expression->dql);

                foreach ($expression->parameters as $name => $value) {
                    $qb->setParameter($name, $value);
                }
            }

        } finally {
            $this->currentMetadata = null;
            $this->query = null;
            $this->joinedForFilter = [];
        }
    }

    /**
     * Recursively compile AST node into DQL expression.
     */
    private function compileNode(Node $node, string $rootAlias, AbstractPlatform $platform): ?\AlexFigures\JsonApi\Filter\Operator\DoctrineExpression
    {
        if ($node instanceof \AlexFigures\JsonApi\Filter\Ast\Group) {
            return $this->compileNode($node->expression, $rootAlias, $platform);
        }
        if ($node instanceof \AlexFigures\JsonApi\Filter\Ast\Between) {
            return $this->compileComparison(new Comparison($node->fieldPath, 'between', [$node->from, $node->to]), $rootAlias, $platform);
        }
        if ($node instanceof \AlexFigures\JsonApi\Filter\Ast\NullCheck) {
            if ($this->filterHandlers->findHandler($node->fieldPath, $node->isNull ? 'null' : 'nnull') !== null) {
                return $this->compileComparison(new Comparison($node->fieldPath, $node->isNull ? 'null' : 'nnull', []), $rootAlias, $platform);
            }
            return new \AlexFigures\JsonApi\Filter\Operator\DoctrineExpression(
                $this->buildDqlFieldPath($rootAlias, $node->fieldPath) . ($node->isNull ? ' IS NULL' : ' IS NOT NULL'),
                []
            );
        }
        if ($node instanceof Comparison) {
            return $this->compileComparison($node, $rootAlias, $platform);
        }

        if ($node instanceof Conjunction) {
            return $this->compileConjunction($node, $rootAlias, $platform);
        }

        if ($node instanceof Disjunction) {
            return $this->compileDisjunction($node, $rootAlias, $platform);
        }

        throw new \InvalidArgumentException(sprintf('Unsupported AST node type: %s', $node::class));
    }

    private function compileComparison(Comparison $node, string $rootAlias, AbstractPlatform $platform): \AlexFigures\JsonApi\Filter\Operator\DoctrineExpression
    {
        // Check for custom filter handler first
        $customHandler = $this->filterHandlers->findHandler($node->fieldPath, $node->operator);
        if ($customHandler !== null) {
            \assert($this->query !== null);
            // Preserve the handler API, but collect its predicate as this AST leaf.
            $branch = clone $this->query;
            $branch->resetDQLPart('where');
            $customHandler->handle($node->fieldPath, $node->operator, $node->values, $branch);
            if ($branch->getDQLPart('where') === null) {
                throw new \LogicException('A custom filter handler must supply a WHERE predicate.');
            }
            $prefix = 'jsonapi_filter_' . ++$this->leaf . '_';
            $aliases = [];
            foreach ($branch->getAllAliases() as $alias) {
                $aliases[$alias] = $prefix . $alias;
            }
            $entityClass = $branch->getRootEntities()[0];
            $field = $branch->getEntityManager()->getClassMetadata($entityClass)->getSingleIdentifierFieldName();
            $branch->select($rootAlias . '.' . $field)->resetDQLPart('orderBy')->setFirstResult(0)->setMaxResults(null);
            $dql = $branch->getDQL();
            $renamed = [];
            // Only bind parameters referenced by this branch (the cloned root may contain others).
            $lexer = new \Doctrine\ORM\Query\Lexer($dql);
            while ($lexer->moveNext()) {
                $token = $lexer->lookahead;
                if ($token->type !== \Doctrine\ORM\Query\TokenType::T_INPUT_PARAMETER) {
                    continue;
                }
                $name = substr($token->value, 1);
                $parameter = $branch->getParameter($name);
                if ($parameter === null) {
                    throw new \LogicException('Custom filter parameter has no value: ' . $name);
                }
                $renamed[$name] = $prefix . $name;
                /** @var \Doctrine\DBAL\ArrayParameterType|\Doctrine\DBAL\ParameterType|int|string|null $parameterType */
                $parameterType = $parameter->getType();
                $this->query->setParameter($prefix . $name, $parameter->getValue(), $parameterType);
            }
            $dql = \AlexFigures\JsonApi\Bridge\Doctrine\Query\DqlRewriter::rewrite($dql, $aliases, $renamed);
            // A handler's inner joins belong to this leaf, never to the alternative OR branches.
            return new \AlexFigures\JsonApi\Filter\Operator\DoctrineExpression($rootAlias . '.' . $field . ' IN (' . $dql . ')', []);
        }

        $operator = $this->operators->get($node->operator);

        // Build DQL field path (e.g., "e.name" or "e.author.name")
        $dqlField = $this->buildDqlFieldPath($rootAlias, $node->fieldPath);

        return $operator->compile($rootAlias, $dqlField, $node->values, $platform);
    }

    private function compileConjunction(Conjunction $node, string $rootAlias, AbstractPlatform $platform): ?\AlexFigures\JsonApi\Filter\Operator\DoctrineExpression
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

        return new \AlexFigures\JsonApi\Filter\Operator\DoctrineExpression($dql, $allParameters);
    }

    private function compileDisjunction(Disjunction $node, string $rootAlias, AbstractPlatform $platform): ?\AlexFigures\JsonApi\Filter\Operator\DoctrineExpression
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

        return new \AlexFigures\JsonApi\Filter\Operator\DoctrineExpression($dql, $allParameters);
    }

    /**
     * Build DQL field path from root alias and field path.
     *
     * Resolves propertyPath aliases before building the DQL path.
     *
     * Examples:
     * - "name" -> "e.name"
     * - "author.id" -> "filter_author.id" (uses JOIN alias)
     * - "tags.id" -> "filter_tags.id" (uses JOIN alias)
     * - "specialTags.name" with propertyPath="articleSpecialTags.specialTag"
     *   -> "filter_articleSpecialTags_specialTag.name"
     */
    private function buildDqlFieldPath(string $rootAlias, string $fieldPath): string
    {
        // Resolve propertyPath aliases (e.g., "specialTags.name" → "articleSpecialTags.specialTag.name")
        $resolvedPath = $this->currentMetadata?->resolveFieldPath($fieldPath) ?? $fieldPath;

        // Check if this is a relationship field path (e.g., "author.id")
        if (str_contains($resolvedPath, '.')) {
            $segments = explode('.', $resolvedPath);
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
        return $rootAlias . '.' . $resolvedPath;
    }

    /**
     * Create JOINs for all relationship paths in the filter AST.
     */
    private function createJoinsForFilter(QueryBuilder $qb, Node $ast, string $rootAlias, ?ResourceMetadata $metadata): void
    {
        $this->collectRelationshipPaths($ast, $qb, $rootAlias, $metadata);
    }

    /**
     * Recursively collect relationship paths from the AST and create JOINs.
     */
    private function collectRelationshipPaths(Node $node, QueryBuilder $qb, string $rootAlias, ?ResourceMetadata $metadata): void
    {
        if ($node instanceof \AlexFigures\JsonApi\Filter\Ast\Group) {
            $this->collectRelationshipPaths($node->expression, $qb, $rootAlias, $metadata);
        } elseif ($node instanceof Comparison || $node instanceof \AlexFigures\JsonApi\Filter\Ast\Between || $node instanceof \AlexFigures\JsonApi\Filter\Ast\NullCheck) {
            $operator = $node instanceof Comparison ? $node->operator : ($node instanceof \AlexFigures\JsonApi\Filter\Ast\Between ? 'between' : ($node->isNull ? 'null' : 'nnull'));
            if ($this->filterHandlers->findHandler($node->fieldPath, $operator) === null) {
                $this->createJoinForFieldPath($qb, $rootAlias, $node->fieldPath, $metadata);
            }
        } elseif ($node instanceof Conjunction || $node instanceof Disjunction) {
            foreach ($node->children as $child) {
                $this->collectRelationshipPaths($child, $qb, $rootAlias, $metadata);
            }
        }
    }

    /**
     * Create JOIN for a field path if it contains relationships.
     *
     * Resolves propertyPath aliases before creating JOINs.
     * For example, if "specialTags.name" has propertyPath="articleSpecialTags.specialTag",
     * it will create JOINs for "articleSpecialTags" and "specialTag" instead.
     */
    private function createJoinForFieldPath(QueryBuilder $qb, string $rootAlias, string $fieldPath, ?ResourceMetadata $metadata): void
    {
        // Resolve propertyPath aliases (e.g., "specialTags.name" → "articleSpecialTags.specialTag.name")
        $resolvedPath = $metadata?->resolveFieldPath($fieldPath) ?? $fieldPath;

        // Check if this is a relationship field path (e.g., "author.id")
        if (!str_contains($resolvedPath, '.')) {
            return; // Direct field, no JOIN needed
        }

        $segments = explode('.', $resolvedPath);
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
