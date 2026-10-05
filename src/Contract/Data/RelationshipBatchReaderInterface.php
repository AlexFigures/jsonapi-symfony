<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Contract\Data;

use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Query\Fetch\RelationshipReadMap;
use AlexFigures\Symfony\Query\Fetch\RelationshipReadRequirements;
use Symfony\Component\HttpFoundation\Request;

/** Optional computed relationship loading. Implement bounded probes and honor application scopes. */
interface RelationshipBatchReaderInterface
{
    public function supports(string $type, string $relationship): bool;

    public function read(RelationshipReadRequirements $requirements, Criteria $criteria, Request $request): RelationshipReadMap;
}
