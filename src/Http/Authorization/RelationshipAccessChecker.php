<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Authorization;

use AlexFigures\JsonApi\Http\Exception\ForbiddenException;
use Symfony\Component\HttpFoundation\Request;

/** @internal */
final readonly class RelationshipAccessChecker
{
    public function __construct(private ?RelationshipAuthorizerInterface $authorizer = null)
    {
    }

    public function assertGranted(Request $request, string $type, string $id, string $relationship, RelationshipOperation $operation): void
    {
        if ($this->authorizer !== null && !$this->authorizer->isGranted($request, $type, $id, $relationship, $operation)) {
            throw new ForbiddenException('Access to this relationship operation is forbidden.');
        }
    }
}
