<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Controller;

use AlexFigures\Symfony\Http\Exception\NotFoundException;
use AlexFigures\Symfony\Resource\Definition\ResourceOperation;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles OPTIONS requests for JSON:API resources.
 *
 * Returns allowed HTTP methods based on the resource's allowed operations.
 */
final class OptionsController
{
    public function __construct(
        private readonly ResourceRegistryInterface $registry,
    ) {
    }

    /**
     * Handle OPTIONS request for collection endpoint.
     *
     * Returns allowed methods for /api/{type} based on INDEX and CREATE operations.
     */
    public function collection(string $type): Response
    {
        if (!$this->registry->hasType($type)) {
            throw new NotFoundException(sprintf('Resource type "%s" not found.', $type));
        }

        $metadata = $this->registry->getByType($type);
        $allowedMethods = $this->collectAllowedMethods($metadata->allowedOperations, [
            ResourceOperation::INDEX,
            ResourceOperation::CREATE,
        ]);

        // Always include OPTIONS itself
        $allowedMethods[] = 'OPTIONS';
        $allowedMethods = array_values(array_unique($allowedMethods));
        sort($allowedMethods);

        return new Response(
            null,
            Response::HTTP_NO_CONTENT,
            ['Allow' => implode(', ', $allowedMethods)]
        );
    }

    /**
     * Handle OPTIONS request for resource endpoint.
     *
     * Returns allowed methods for /api/{type}/{id} based on SHOW, UPDATE, and DELETE operations.
     */
    public function resource(string $type): Response
    {
        if (!$this->registry->hasType($type)) {
            throw new NotFoundException(sprintf('Resource type "%s" not found.', $type));
        }

        $metadata = $this->registry->getByType($type);
        $allowedMethods = $this->collectAllowedMethods($metadata->allowedOperations, [
            ResourceOperation::SHOW,
            ResourceOperation::UPDATE,
            ResourceOperation::DELETE,
        ]);

        // Always include OPTIONS itself
        $allowedMethods[] = 'OPTIONS';
        $allowedMethods = array_values(array_unique($allowedMethods));
        sort($allowedMethods);

        return new Response(
            null,
            Response::HTTP_NO_CONTENT,
            ['Allow' => implode(', ', $allowedMethods)]
        );
    }

    /**
     * Handle OPTIONS request for related resource endpoint.
     *
     * Returns allowed methods for /api/{type}/{id}/{rel} (always GET, HEAD for related resources).
     */
    public function related(string $type): Response
    {
        if (!$this->registry->hasType($type)) {
            throw new NotFoundException(sprintf('Resource type "%s" not found.', $type));
        }

        $metadata = $this->registry->getByType($type);
        $allowedMethods = $this->collectAllowedMethods($metadata->allowedOperations, [
            ResourceOperation::SHOW,
        ]);

        // Always include OPTIONS itself
        $allowedMethods[] = 'OPTIONS';
        $allowedMethods = array_values(array_unique($allowedMethods));
        sort($allowedMethods);

        return new Response(
            null,
            Response::HTTP_NO_CONTENT,
            ['Allow' => implode(', ', $allowedMethods)]
        );
    }

    /**
     * Handle OPTIONS request for relationship endpoint.
     *
     * Returns allowed methods for /api/{type}/{id}/relationships/{rel}.
     * GET/HEAD requires SHOW operation, write methods require UPDATE operation.
     */
    public function relationship(string $type): Response
    {
        if (!$this->registry->hasType($type)) {
            throw new NotFoundException(sprintf('Resource type "%s" not found.', $type));
        }

        $metadata = $this->registry->getByType($type);
        $allowedMethods = [];

        // SHOW operation allows GET and HEAD
        if ($this->hasOperation(ResourceOperation::SHOW, $metadata->allowedOperations)) {
            $allowedMethods = array_merge($allowedMethods, ['GET', 'HEAD']);
        }

        // UPDATE operation allows PATCH, POST, DELETE
        if ($this->hasOperation(ResourceOperation::UPDATE, $metadata->allowedOperations)) {
            $allowedMethods = array_merge($allowedMethods, ['PATCH', 'POST', 'DELETE']);
        }

        // Always include OPTIONS itself
        $allowedMethods[] = 'OPTIONS';
        $allowedMethods = array_values(array_unique($allowedMethods));
        sort($allowedMethods);

        return new Response(
            null,
            Response::HTTP_NO_CONTENT,
            ['Allow' => implode(', ', $allowedMethods)]
        );
    }

    /**
     * Collect allowed HTTP methods from operations.
     *
     * @param list<ResourceOperation> $allowedOperations
     * @param list<ResourceOperation> $relevantOperations
     *
     * @return list<string>
     */
    private function collectAllowedMethods(array $allowedOperations, array $relevantOperations): array
    {
        $methods = [];

        foreach ($relevantOperations as $operation) {
            if ($this->hasOperation($operation, $allowedOperations)) {
                $methods = array_merge($methods, $operation->httpMethods());
            }
        }

        return array_values(array_unique($methods));
    }

    /**
     * Check if operation is in allowed operations list.
     *
     * @param list<ResourceOperation> $allowedOperations
     */
    private function hasOperation(ResourceOperation $operation, array $allowedOperations): bool
    {
        foreach ($allowedOperations as $allowed) {
            if ($allowed === $operation) {
                return true;
            }
        }

        return false;
    }
}
