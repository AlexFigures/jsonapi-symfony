<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Bridge\Symfony\Routing;

/**
 * Generates route names for JSON:API resources based on configured naming convention.
 *
 * Supports different naming conventions:
 * - snake_case: jsonapi.blog_posts.index (default, backward compatible)
 * - kebab-case: jsonapi.blog-posts.index
 *
 * @internal
 */
final class RouteNameGenerator
{
    public const SNAKE_CASE = 'snake_case';
    public const KEBAB_CASE = 'kebab-case';

    public function __construct(
        private readonly string $namingConvention = self::SNAKE_CASE
    ) {
    }

    /**
     * Generate a route name for a resource action.
     *
     * @param string $resourceType The resource type (e.g., 'blog_posts', 'articles')
     * @param string|null $action The action (e.g., 'index', 'show', 'create') - null for relationship routes
     * @param string|null $relationship Optional relationship name for relationship routes
     * @param string|null $relationshipAction Optional relationship action (e.g., 'show', 'add', 'remove')
     */
    public function generateRouteName(
        string $resourceType,
        ?string $action,
        ?string $relationship = null,
        ?string $relationshipAction = null
    ): string {
        $transformedType = $this->transformResourceType($resourceType);

        if ($relationship !== null) {
            $transformedRelationship = $this->transformResourceType($relationship);

            if ($relationshipAction !== null) {
                // Relationship action routes: jsonapi.articles.relationships.author.show
                return "jsonapi.{$transformedType}.relationships.{$transformedRelationship}.{$relationshipAction}";
            }

            // Related resource routes: jsonapi.articles.related.author
            return "jsonapi.{$transformedType}.related.{$transformedRelationship}";
        }

        if ($action === null) {
            throw new \InvalidArgumentException('Action cannot be null for non-relationship routes');
        }

        // Standard resource routes: jsonapi.articles.index
        return "jsonapi.{$transformedType}.{$action}";
    }

    /**
     * Transform a resource type according to the configured naming convention.
     */
    private function transformResourceType(string $resourceType): string
    {
        return match ($this->namingConvention) {
            self::KEBAB_CASE => $this->toKebabCase($resourceType),
            self::SNAKE_CASE => $resourceType, // Keep as-is for backward compatibility
            default => $resourceType,
        };
    }

    /**
     * Convert a string to kebab-case.
     * 
     * Handles various input formats:
     * - snake_case: blog_posts -> blog-posts
     * - camelCase: blogPosts -> blog-posts
     * - PascalCase: BlogPosts -> blog-posts
     * - Already kebab-case: blog-posts -> blog-posts
     */
    private function toKebabCase(string $input): string
    {
        // Handle camelCase and PascalCase by inserting hyphens before uppercase letters
        $result = preg_replace('/([a-z])([A-Z])/', '$1-$2', $input);
        
        // Replace underscores with hyphens
        $result = str_replace('_', '-', $result);
        
        // Convert to lowercase
        return strtolower($result);
    }
}
