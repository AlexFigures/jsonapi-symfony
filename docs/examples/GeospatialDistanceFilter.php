<?php

declare(strict_types=1);

namespace App\Filter;

use Doctrine\ORM\QueryBuilder;
use JsonApi\Symfony\Filter\Ast\Comparison;
use JsonApi\Symfony\Filter\Handler\FilterHandlerInterface;

/**
 * Example custom filter handler for geospatial distance filtering.
 *
 * This handler demonstrates how to implement location-based filtering
 * using latitude and longitude coordinates.
 *
 * Usage in resource:
 * ```php
 * #[JsonApiResource(type: 'locations')]
 * #[FilterableFields([
 *     new FilterableField('distance', customHandler: GeospatialDistanceFilter::class),
 *     new FilterableField('name', operators: ['eq', 'like']),
 * ])]
 * class Location {}
 * ```
 *
 * API request:
 * GET /api/locations?filter[distance][lte]=50.123,14.456,10
 * (latitude, longitude, radius in km)
 */
final class GeospatialDistanceFilter implements FilterHandlerInterface
{
    public function supports(string $field, string $operator): bool
    {
        return $field === 'distance' && in_array($operator, ['lte', 'lt'], true);
    }

    public function handle(string $field, string $operator, array $values, object $queryBuilder): void
    {
        if (!$queryBuilder instanceof QueryBuilder) {
            throw new \InvalidArgumentException('Expected Doctrine QueryBuilder');
        }

        $coordinates = $values[0] ?? '';
        if ($coordinates === '') {
            return;
        }

        // Parse coordinates: "latitude,longitude,radius"
        $parts = explode(',', $coordinates);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('Distance filter requires format: latitude,longitude,radius');
        }

        $latitude = (float) $parts[0];
        $longitude = (float) $parts[1];
        $radius = (float) $parts[2];

        $rootAliases = $queryBuilder->getRootAliases();
        $rootAlias = $rootAliases[0] ?? 'e';

        // Haversine formula for calculating distance
        $distanceFormula = sprintf(
            '(6371 * ACOS(COS(RADIANS(:lat)) * COS(RADIANS(%s.latitude)) * COS(RADIANS(%s.longitude) - RADIANS(:lng)) + SIN(RADIANS(:lat)) * SIN(RADIANS(%s.latitude))))',
            $rootAlias,
            $rootAlias,
            $rootAlias
        );

        $comparison = $operator === 'lte' ? '<=' : '<';
        $queryBuilder->andWhere("$distanceFormula $comparison :radius");

        $queryBuilder->setParameter('lat', $latitude);
        $queryBuilder->setParameter('lng', $longitude);
        $queryBuilder->setParameter('radius', $radius);
    }

    public function getPriority(): int
    {
        return 0;
    }
}
