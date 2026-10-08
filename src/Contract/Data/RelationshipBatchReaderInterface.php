<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Contract\Data;

use AlexFigures\JsonApi\Query\Criteria;
use AlexFigures\JsonApi\Query\Fetch\RelationshipReadMap;
use AlexFigures\JsonApi\Query\Fetch\RelationshipReadRequirements;
use Symfony\Component\HttpFoundation\Request;

/** Optional computed relationship loading. Implement bounded probes and honor application scopes.
 * @api
 */
interface RelationshipBatchReaderInterface
{
    public function supports(string $type, string $relationship): bool;

    public function read(RelationshipReadRequirements $requirements, Criteria $criteria, Request $request): RelationshipReadMap;
}
