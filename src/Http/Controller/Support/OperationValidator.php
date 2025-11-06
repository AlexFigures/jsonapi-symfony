<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Controller\Support;

use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Exception\MethodNotAllowedException;
use AlexFigures\Symfony\Resource\Definition\ResourceOperation;

/**
 * Validates that a requested operation is allowed for a resource.
 *
 * This service encapsulates the logic for checking if a specific operation
 * (e.g., CREATE, UPDATE, DELETE) is permitted based on the resource's
 * allowed operations configuration.
 */
final class OperationValidator
{
    public function __construct(
        private readonly ErrorMapper $errors,
    ) {
    }

    /**
     * Assert that an operation is allowed for the resource.
     *
     * @param list<ResourceOperation> $allowedOperations
     *
     * @throws MethodNotAllowedException if the operation is not allowed
     */
    public function assertAllowed(
        ResourceOperation $operation,
        array $allowedOperations
    ): void {
        foreach ($allowedOperations as $allowed) {
            if ($allowed === $operation) {
                return;
            }
        }

        $allowedMethods = $this->extractAllowedMethods($allowedOperations);
        $error = $this->errors->methodNotAllowed($allowedMethods);

        throw new MethodNotAllowedException(
            $allowedMethods,
            'Operation not allowed',
            [$error]
        );
    }

    /**
     * Extract all allowed HTTP methods from the allowed operations.
     *
     * @param list<ResourceOperation> $operations
     *
     * @return list<string>
     */
    private function extractAllowedMethods(array $operations): array
    {
        $methods = [];
        foreach ($operations as $operation) {
            $methods = array_merge($methods, $operation->httpMethods());
        }

        return array_values(array_unique($methods));
    }
}

