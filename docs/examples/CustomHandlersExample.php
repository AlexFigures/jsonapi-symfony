<?php

declare(strict_types=1);

namespace App\Entity;

use AlexFigures\Symfony\Resource\Attribute\FilterableField;
use AlexFigures\Symfony\Resource\Attribute\FilterableFields;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use AlexFigures\Symfony\Resource\Attribute\SortableFields;
use App\Filter\FullTextSearchFilter;
use App\Filter\GeospatialDistanceFilter;
use App\Sort\RelevanceSorter;

/**
 * Example entity demonstrating the use of custom filter and sort handlers.
 *
 * This example shows how to:
 * 1. Configure filterable fields with custom handlers
 * 2. Mix custom handlers with standard operators
 * 3. Use custom sort handlers alongside standard sorting
 *
 * API Examples:
 *
 * Full-text search:
 * GET /api/articles?filter[search][eq]=symfony
 *
 * Geospatial filtering:
 * GET /api/articles?filter[distance][lte]=50.123,14.456,10
 *
 * Standard filtering:
 * GET /api/articles?filter[status][eq]=published&filter[viewCount][gte]=100
 *
 * Custom sorting:
 * GET /api/articles?sort=-relevance
 *
 * Combined example:
 * GET /api/articles?filter[search][eq]=symfony&filter[status][eq]=published&sort=-relevance
 */
#[JsonApiResource(type: 'articles')]
#[FilterableFields([
    // Custom full-text search across multiple fields
    new FilterableField('search', customHandler: FullTextSearchFilter::class),
    
    // Custom geospatial distance filtering
    new FilterableField('distance', customHandler: GeospatialDistanceFilter::class),
    
    // Standard filtering with restricted operators
    new FilterableField('status', operators: ['eq', 'in']),
    new FilterableField('viewCount', operators: ['eq', 'gt', 'gte', 'lt', 'lte']),
    new FilterableField('createdAt', operators: ['gte', 'lte', 'between']),
    new FilterableField('isPublished', operators: ['eq']),
    
    // Allow all operators for these fields
    'title',
    'authorId',
])]
#[SortableFields([
    'title',
    'createdAt', 
    'viewCount',
    'relevance', // This will be handled by RelevanceSorter
])]
class Article
{
    private int $id;
    private string $title;
    private string $content;
    private string $summary;
    private string $status;
    private int $viewCount;
    private \DateTimeInterface $createdAt;
    private bool $isPublished;
    private int $authorId;
    private float $latitude;
    private float $longitude;

    // ... getters and setters
}
