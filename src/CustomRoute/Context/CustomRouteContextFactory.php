<?php

declare(strict_types=1);

namespace JsonApi\Symfony\CustomRoute\Context;

use JsonApi\Symfony\Contract\Data\ResourceRepository;
use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Exception\BadRequestException;
use JsonApi\Symfony\Http\Exception\NotFoundException;
use JsonApi\Symfony\Http\Request\QueryParser;
use JsonApi\Symfony\Query\Criteria;
use JsonApi\Symfony\Resource\Metadata\CustomRouteMetadata;
use JsonApi\Symfony\Resource\Registry\CustomRouteRegistryInterface;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

/**
 * Factory for creating CustomRouteContext instances.
 *
 * This factory is responsible for:
 * - Loading resources for routes with {id} parameter
 * - Parsing request body (JSON)
 * - Parsing JSON:API query criteria (includes, fields, filters, sorting, pagination)
 * - Extracting route parameters
 * - Building the context object
 *
 * @internal
 */
final class CustomRouteContextFactory
{
    public function __construct(
        private readonly CustomRouteRegistryInterface $customRouteRegistry,
        private readonly ResourceRegistryInterface $resourceRegistry,
        private readonly ResourceRepository $repository,
        private readonly QueryParser $queryParser,
        private readonly ErrorMapper $errorMapper,
    ) {
    }

    /**
     * Create a CustomRouteContext from a request and route name.
     *
     * @param Request $request   The HTTP request
     * @param string  $routeName The route name (e.g., 'articles.publish')
     *
     * @return CustomRouteContext The created context
     *
     * @throws NotFoundException   if the route or resource type is not found
     * @throws BadRequestException if the request body is malformed
     */
    public function create(Request $request, string $routeName): CustomRouteContext
    {
        $routeMetadata = $this->findRouteMetadata($routeName);
        $resourceType = $this->resolveResourceType($routeMetadata);
        $routeParams = $this->extractRouteParams($request);

        // Parse JSON:API query criteria first (needed for resource loading)
        $criteria = $this->parseQueryCriteria($resourceType, $request);

        // Pre-load resource if route has {id} parameter (using parsed criteria)
        $resource = $this->loadResourceIfNeeded($resourceType, $routeParams, $criteria);

        // Parse request body
        $body = $this->parseRequestBody($request);

        return new CustomRouteContext(
            request: $request,
            resource: $resource,
            resourceType: $resourceType,
            routeParams: $routeParams,
            criteria: $criteria,
            body: $body,
            repository: $this->repository,
        );
    }

    /**
     * Find route metadata by route name.
     *
     * @throws NotFoundException if route is not found
     */
    private function findRouteMetadata(string $routeName): CustomRouteMetadata
    {
        foreach ($this->customRouteRegistry->all() as $route) {
            if ($route->name === $routeName) {
                return $route;
            }
        }

        throw new NotFoundException(sprintf('Custom route "%s" not found.', $routeName));
    }

    /**
     * Resolve the resource type from route metadata.
     *
     * @throws NotFoundException if resource type cannot be determined
     */
    private function resolveResourceType(CustomRouteMetadata $routeMetadata): string
    {
        $resourceType = $routeMetadata->resourceType;

        if ($resourceType === null) {
            throw new NotFoundException(
                'Resource type not specified in route metadata. ' .
                'Use the resourceType parameter in #[JsonApiCustomRoute].'
            );
        }

        if (!$this->resourceRegistry->hasType($resourceType)) {
            throw new NotFoundException(sprintf('Resource type "%s" not found.', $resourceType));
        }

        return $resourceType;
    }

    /**
     * Extract route parameters from the request.
     *
     * @return array<string, mixed>
     */
    private function extractRouteParams(Request $request): array
    {
        $params = $request->attributes->get('_route_params', []);

        return is_array($params) ? $params : [];
    }

    /**
     * Load resource if the route has an {id} parameter.
     *
     * Uses the parsed criteria to support includes and sparse fieldsets
     * for the pre-loaded resource.
     *
     * @param array<string, mixed> $routeParams
     *
     * @return object|null The loaded resource or null if no {id} parameter
     *
     * @throws NotFoundException if resource with given ID is not found
     */
    private function loadResourceIfNeeded(string $resourceType, array $routeParams, Criteria $criteria): ?object
    {
        // Check if route has an {id} parameter
        if (!isset($routeParams['id'])) {
            return null;
        }

        $idValue = $routeParams['id'];

        // Ensure ID is a string or can be converted to string
        if (!is_scalar($idValue)) {
            throw new BadRequestException('Route parameter "id" must be a scalar value.');
        }

        $id = (string) $idValue;

        // Load the resource using parsed criteria (supports includes and sparse fieldsets)
        $resource = $this->repository->findOne($resourceType, $id, $criteria);

        if ($resource === null) {
            throw new NotFoundException(sprintf(
                'Resource "%s" with id "%s" not found.',
                $resourceType,
                $id
            ));
        }

        return $resource;
    }

    /**
     * Parse JSON:API query criteria from the request.
     */
    private function parseQueryCriteria(string $resourceType, Request $request): Criteria
    {
        try {
            return $this->queryParser->parse($resourceType, $request);
        } catch (Throwable $e) {
            // QueryParser already throws proper JSON:API exceptions, just re-throw
            throw $e;
        }
    }

    /**
     * Parse and decode the request body.
     *
     * @return array<string, mixed>
     *
     * @throws BadRequestException if the body is malformed JSON
     */
    private function parseRequestBody(Request $request): array
    {
        $content = (string) $request->getContent();

        // Empty body is valid (returns empty array)
        if ($content === '') {
            return [];
        }

        try {
            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            $error = $this->errorMapper->invalidJson($e);
            throw new BadRequestException('Malformed JSON in request body.', [$error], previous: $e);
        }

        if (!is_array($decoded)) {
            $error = $this->errorMapper->invalidPointer('/', 'Request body must be a JSON object.');
            throw new BadRequestException('Invalid request body.', [$error]);
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
