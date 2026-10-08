<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Contract\Data;

use AlexFigures\JsonApi\Query\Criteria;
use AlexFigures\JsonApi\Query\Fetch\RelationshipReadMap;
use Symfony\Component\HttpFoundation\Request;

/** Optional persistence capability; the returned data is local to one document build.
 * @api
 */
interface RepresentationPreloaderInterface
{
    /** @param list<object> $models */
    public function preload(string $type, array $models, Criteria $criteria, Request $request): RelationshipReadMap;
}
