<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Controller;

use AlexFigures\JsonApi\Http\Exception\NotFoundException;
use AlexFigures\JsonApi\Resource\Definition\ResourceOperation;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles OPTIONS requests for JSON:API resources.
 *
 * Returns allowed HTTP methods based on the resource's allowed operations.
 * @internal
 */
final readonly class OptionsController
{
    public function __construct(
        private ResourceRegistryInterface $registry,
        private bool $headEnabled = true,
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
        $allowedMethods = array_values(array_unique($this->filterMethods($allowedMethods)));
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
        $allowedMethods = array_values(array_unique($this->filterMethods($allowedMethods)));
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
        $allowedMethods = array_values(array_unique($this->filterMethods($allowedMethods)));
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
    public function relationship(string $type, ?string $rel = null): Response
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
            if ($rel !== null && !isset($metadata->relationships[$rel])) {
                throw new NotFoundException('Relationship not found.');
            }
            $toMany = $rel === null || $metadata->relationships[$rel]->toMany;
            $allowedMethods = array_merge($allowedMethods, $toMany ? ['PATCH', 'POST', 'DELETE'] : ['PATCH']);
        }

        // Always include OPTIONS itself
        $allowedMethods[] = 'OPTIONS';
        $allowedMethods = array_values(array_unique($this->filterMethods($allowedMethods)));
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

        return array_values(array_unique($this->filterMethods($methods)));
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
    /** @param list<string> $methods
     * @return list<string>
     */
    private function filterMethods(array $methods): array
    {
        return $this->headEnabled ? $methods : array_values(array_diff($methods, ['HEAD']));
    }

}
