<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Authorization;

use Symfony\Component\HttpFoundation\Request;

/**
 * Application policy for standalone relationship endpoints.
 *
 * Policies must be side-effect free: writes with preconditions can be checked
 * before acquiring concurrency protection and again before mutation.
 * @api
 */
interface RelationshipAuthorizerInterface
{
    public function isGranted(Request $request, string $type, string $id, string $relationship, RelationshipOperation $operation): bool;
}
