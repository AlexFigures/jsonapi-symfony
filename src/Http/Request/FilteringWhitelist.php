<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Request;

use JsonApi\Symfony\Filter\Ast\Node;
use JsonApi\Symfony\Filter\Ast\Between;
use JsonApi\Symfony\Filter\Ast\Comparison;
use JsonApi\Symfony\Filter\Ast\Conjunction;
use JsonApi\Symfony\Filter\Ast\Disjunction;
use JsonApi\Symfony\Filter\Ast\Group;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use JsonApi\Symfony\Filter\Ast\NullCheck;
use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Exception\BadRequestException;

final class FilteringWhitelist
{
    public function __construct(
        private ResourceRegistryInterface $registry,
        private ErrorMapper $errors,
    ) {
    }

    /**
     * Validate that all fields and operators in the filter AST are allowed.
     *
     * @throws BadRequestException if any field or operator is not allowed
     */
    public function validate(string $type, ?Node $filterNode): void
    {
        if ($filterNode === null) {
            return;
        }

        if (!$this->registry->hasType($type)) {
            return; // Type validation is handled elsewhere
        }

        $metadata = $this->registry->getByType($type);
        $filterableFields = $metadata->filterableFields;

        if ($filterableFields === null) {
            // No FilterableFields attribute defined - reject all filtering
            $this->throwFilterNotAllowed($type, 'No filterable fields defined for this resource');
        }

        $this->validateNode($type, $filterNode, $filterableFields);
    }

    /**
     * Returns the list of fields allowed for filtering for a given resource type.
     *
     * @return list<string>
     */
    public function allowedFor(string $type): array
    {
        if (!$this->registry->hasType($type)) {
            return [];
        }

        $metadata = $this->registry->getByType($type);
        return $metadata->filterableFields?->getAllowedFields() ?? [];
    }

    /**
     * Check if a field is allowed for filtering.
     */
    public function isFieldAllowed(string $type, string $field): bool
    {
        if (!$this->registry->hasType($type)) {
            return false;
        }

        $metadata = $this->registry->getByType($type);
        return $metadata->filterableFields?->isAllowed($field) ?? false;
    }

    /**
     * Check if an operator is allowed for a specific field.
     */
    public function isOperatorAllowed(string $type, string $field, string $operator): bool
    {
        if (!$this->registry->hasType($type)) {
            return false;
        }

        $metadata = $this->registry->getByType($type);
        return $metadata->filterableFields?->isOperatorAllowed($field, $operator) ?? false;
    }

    /**
     * Recursively validate a filter AST node.
     *
     * SECURITY: This method must handle ALL possible AST node types to prevent
     * whitelist bypass attacks. Missing node types allow attackers to use
     * disallowed fields/operators through unvalidated node types.
     */
    private function validateNode(string $type, Node $node, \JsonApi\Symfony\Resource\Attribute\FilterableFields $filterableFields): void
    {
        if ($node instanceof Comparison) {
            $this->validateComparison($type, $node, $filterableFields);
        } elseif ($node instanceof Conjunction) {
            $this->validateConjunction($type, $node, $filterableFields);
        } elseif ($node instanceof Disjunction) {
            $this->validateDisjunction($type, $node, $filterableFields);
        } elseif ($node instanceof NullCheck) {
            $this->validateNullCheck($type, $node, $filterableFields);
        } elseif ($node instanceof Between) {
            $this->validateBetween($type, $node, $filterableFields);
        } elseif ($node instanceof Group) {
            $this->validateGroup($type, $node, $filterableFields);
        } else {
            // SECURITY: Reject unknown node types to prevent future bypass attacks
            throw new BadRequestException(sprintf(
                'Unsupported filter node type "%s" for resource type "%s".',
                get_class($node),
                $type
            ));
        }
    }

    /**
     * Validate a comparison node.
     */
    private function validateComparison(string $type, Comparison $node, \JsonApi\Symfony\Resource\Attribute\FilterableFields $filterableFields): void
    {
        $field = $node->fieldPath;

        if (!$filterableFields->isAllowed($field)) {
            $this->throwFieldNotAllowed($type, $field);
        }

        if (!$filterableFields->isOperatorAllowed($field, $node->operator)) {
            $this->throwOperatorNotAllowed($type, $field, $node->operator);
        }
    }

    /**
     * Validate a conjunction node (AND).
     */
    private function validateConjunction(string $type, Conjunction $node, \JsonApi\Symfony\Resource\Attribute\FilterableFields $filterableFields): void
    {
        foreach ($node->children as $child) {
            $this->validateNode($type, $child, $filterableFields);
        }
    }

    /**
     * Validate a disjunction node (OR).
     */
    private function validateDisjunction(string $type, Disjunction $node, \JsonApi\Symfony\Resource\Attribute\FilterableFields $filterableFields): void
    {
        foreach ($node->children as $child) {
            $this->validateNode($type, $child, $filterableFields);
        }
    }

    /**
     * Validate a null check node (IS NULL / IS NOT NULL).
     */
    private function validateNullCheck(string $type, NullCheck $node, \JsonApi\Symfony\Resource\Attribute\FilterableFields $filterableFields): void
    {
        $field = $node->fieldPath;
        $operator = $node->isNull ? 'null' : 'nnull';

        if (!$filterableFields->isAllowed($field)) {
            $this->throwFieldNotAllowed($type, $field);
        }

        if (!$filterableFields->isOperatorAllowed($field, $operator)) {
            $this->throwOperatorNotAllowed($type, $field, $operator);
        }
    }

    /**
     * Validate a between node (BETWEEN comparison).
     */
    private function validateBetween(string $type, Between $node, \JsonApi\Symfony\Resource\Attribute\FilterableFields $filterableFields): void
    {
        $field = $node->fieldPath;
        $operator = 'between';

        if (!$filterableFields->isAllowed($field)) {
            $this->throwFieldNotAllowed($type, $field);
        }

        if (!$filterableFields->isOperatorAllowed($field, $operator)) {
            $this->throwOperatorNotAllowed($type, $field, $operator);
        }
    }

    /**
     * Validate a group node (parenthesized expression).
     */
    private function validateGroup(string $type, Group $node, \JsonApi\Symfony\Resource\Attribute\FilterableFields $filterableFields): void
    {
        // Groups just wrap another expression, so validate the wrapped expression
        $this->validateNode($type, $node->expression, $filterableFields);
    }

    /**
     * @throws BadRequestException
     */
    private function throwFieldNotAllowed(string $type, string $field): never
    {
        $error = $this->errors->filterFieldNotAllowed($type, $field);
        throw new BadRequestException('Filter field not allowed.', [$error]);
    }

    /**
     * @throws BadRequestException
     */
    private function throwOperatorNotAllowed(string $type, string $field, string $operator): never
    {
        $error = $this->errors->filterOperatorNotAllowed($type, $field, $operator);
        throw new BadRequestException('Filter operator not allowed.', [$error]);
    }

    /**
     * @throws BadRequestException
     */
    private function throwFilterNotAllowed(string $type, string $message): never
    {
        $error = $this->errors->filterNotAllowed($type, $message);
        throw new BadRequestException('Filtering not allowed.', [$error]);
    }
}
