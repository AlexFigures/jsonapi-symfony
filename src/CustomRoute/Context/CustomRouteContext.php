<?php

declare(strict_types=1);

namespace JsonApi\Symfony\CustomRoute\Context;

use JsonApi\Symfony\Contract\Data\ResourceRepository;
use JsonApi\Symfony\CustomRoute\Query\CriteriaBuilder;
use JsonApi\Symfony\Query\Criteria;
use LogicException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Context object providing access to request data and pre-loaded resources.
 *
 * This object is passed to custom route handlers and provides convenient access to:
 * - Pre-loaded resources (for routes with {id} parameter)
 * - Route parameters
 * - Request body (decoded JSON)
 * - Query parameters
 * - Parsed JSON:API query criteria (includes, sparse fieldsets, filters, sorting, pagination)
 * - The underlying HTTP request
 *
 * @api This class is part of the public API and follows semantic versioning.
 * @since 0.3.0
 */
final class CustomRouteContext
{
    /**
     * @param Request              $request      The underlying HTTP request
     * @param object|null          $resource     Pre-loaded resource (null for collection endpoints)
     * @param string               $resourceType The JSON:API resource type for this route
     * @param array<string, mixed> $routeParams  Route parameters from the URL path
     * @param Criteria             $criteria     Parsed JSON:API query criteria (includes, fields, filters, etc.)
     * @param array<string, mixed> $body         Decoded request body (empty array if no body)
     * @param ResourceRepository   $repository   Repository for fetching resources with criteria
     */
    public function __construct(
        private readonly Request $request,
        private readonly ?object $resource,
        private readonly string $resourceType,
        private readonly array $routeParams,
        private readonly Criteria $criteria,
        private readonly array $body,
        private readonly ResourceRepository $repository,
    ) {
    }

    /**
     * Get the pre-loaded resource (for single-resource routes with {id} parameter).
     *
     * The bundle automatically loads the resource for routes that have an {id}
     * parameter in the path. This saves you from having to manually query the
     * repository in every handler.
     *
     * Example:
     * ```php
     * // Route: /articles/{id}/publish
     * $article = $context->getResource(); // Article is already loaded
     * ```
     *
     * @return object The pre-loaded resource entity
     *
     * @throws LogicException if no resource was loaded (e.g., for collection endpoints).
     *                        Use hasResource() to check first.
     */
    public function getResource(): object
    {
        if ($this->resource === null) {
            throw new LogicException(
                'No resource available in this context. This is likely a collection endpoint. ' .
                'Use hasResource() to check before calling getResource().'
            );
        }

        return $this->resource;
    }

    /**
     * Check if a resource was pre-loaded.
     *
     * Returns true for single-resource routes (with {id} parameter), false for
     * collection endpoints or routes without resource loading.
     *
     * @return bool True if a resource is available, false otherwise
     */
    public function hasResource(): bool
    {
        return $this->resource !== null;
    }

    /**
     * Get the JSON:API resource type for this route.
     *
     * This is the resource type specified in the #[JsonApiCustomRoute] attribute
     * or derived from the entity class.
     *
     * @return string The resource type (e.g., 'articles', 'users')
     */
    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    /**
     * Get a specific route parameter by name.
     *
     * Route parameters are extracted from the URL path pattern. For example,
     * for a route with path '/articles/{id}/comments/{commentId}', you can
     * access 'id' and 'commentId'.
     *
     * Example:
     * ```php
     * $id = $context->getParam('id');
     * $commentId = $context->getParam('commentId');
     * ```
     *
     * @param string $name The parameter name
     *
     * @return mixed The parameter value
     *
     * @throws \InvalidArgumentException if the parameter doesn't exist
     */
    public function getParam(string $name): mixed
    {
        if (!array_key_exists($name, $this->routeParams)) {
            throw new \InvalidArgumentException(sprintf(
                'Route parameter "%s" does not exist. Available parameters: %s',
                $name,
                implode(', ', array_keys($this->routeParams))
            ));
        }

        return $this->routeParams[$name];
    }

    /**
     * Get all route parameters.
     *
     * @return array<string, mixed> All route parameters as key-value pairs
     */
    public function getParams(): array
    {
        return $this->routeParams;
    }

    /**
     * Get the underlying HTTP request.
     *
     * Use this when you need access to headers, cookies, or other request data
     * not exposed by the context object.
     *
     * @return Request The Symfony HTTP request object
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Get parsed JSON:API query criteria.
     *
     * The criteria object contains parsed and validated:
     * - Sparse fieldsets (fields[type]=field1,field2)
     * - Includes (include=author,comments)
     * - Filters (filter[status]=published)
     * - Sorting (sort=-createdAt,title)
     * - Pagination (page[number]=2&page[size]=10)
     *
     * This is automatically applied when building collection responses.
     *
     * @return Criteria The parsed query criteria
     */
    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    /**
     * Get the decoded request body.
     *
     * For JSON requests, this is the decoded JSON as an associative array.
     * For requests without a body, this returns an empty array.
     *
     * Example:
     * ```php
     * $body = $context->getBody();
     * $ids = $body['ids'] ?? [];
     * $options = $body['options'] ?? [];
     * ```
     *
     * @return array<string, mixed> The decoded request body
     */
    public function getBody(): array
    {
        return $this->body;
    }

    /**
     * Get a query parameter from the URL.
     *
     * This is a convenience method for accessing query string parameters.
     * For JSON:API query parameters (include, fields, filter, etc.), use
     * getCriteria() instead.
     *
     * Example:
     * ```php
     * // For URL: /articles/search?q=test&limit=10
     * $query = $context->getQueryParam('q');        // 'test'
     * $limit = $context->getQueryParam('limit', 20); // 10
     * $page = $context->getQueryParam('page', 1);    // 1 (default)
     * ```
     *
     * @param string                     $name    The query parameter name
     * @param bool|float|int|string|null $default Default value if parameter doesn't exist
     *
     * @return mixed The parameter value or default
     */
    public function getQueryParam(string $name, bool|float|int|string|null $default = null): mixed
    {
        return $this->request->query->get($name, $default);
    }

    /**
     * Check if a query parameter exists.
     *
     * @param string $name The query parameter name
     *
     * @return bool True if the parameter exists, false otherwise
     */
    public function hasQueryParam(string $name): bool
    {
        return $this->request->query->has($name);
    }

    /**
     * Get a CriteriaBuilder for modifying query criteria.
     *
     * This allows handlers to add custom filters and conditions to the
     * already-parsed JSON:API query parameters. The builder provides a
     * fluent API that's easier to use than manually constructing Filter AST.
     *
     * Example:
     * ```php
     * $criteria = $context->criteria()
     *     ->addFilter('category.id', 'eq', $categoryId)
     *     ->addFilter('status', 'eq', 'published')
     *     ->build();
     *
     * $slice = $context->getRepository()->findCollection('articles', $criteria);
     * ```
     *
     * For complex conditions:
     * ```php
     * $criteria = $context->criteria()
     *     ->addCustomCondition(function($qb) use ($categoryId) {
     *         $qb->andWhere('e.category = :cat')
     *            ->setParameter('cat', $categoryId);
     *     })
     *     ->build();
     * ```
     *
     * @return CriteriaBuilder Builder for modifying criteria
     *
     * @since 0.3.0
     */
    public function criteria(): CriteriaBuilder
    {
        return new CriteriaBuilder($this->criteria);
    }

    /**
     * Get the repository for fetching resources.
     *
     * Use this to fetch collections or single resources with automatic
     * application of filters, sorting, pagination, and includes from
     * the query string.
     *
     * Example:
     * ```php
     * // Fetch collection with all query parameters applied
     * $slice = $context->getRepository()->findCollection('articles', $context->getCriteria());
     *
     * // Or with modified criteria
     * $criteria = $context->criteria()
     *     ->addFilter('status', 'eq', 'published')
     *     ->build();
     * $slice = $context->getRepository()->findCollection('articles', $criteria);
     * ```
     *
     * @return ResourceRepository The repository for resource operations
     *
     * @since 0.3.0
     */
    public function getRepository(): ResourceRepository
    {
        return $this->repository;
    }
}
