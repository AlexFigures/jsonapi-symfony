<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Contract\Data;

use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Query\Fetch\RelationshipReadMap;
use Symfony\Component\HttpFoundation\Request;

/** Optional persistence capability; the returned data is local to one document build. */
interface RepresentationPreloaderInterface
{
    /** @param list<object> $models */
    public function preload(string $type, array $models, Criteria $criteria, Request $request): RelationshipReadMap;
}
