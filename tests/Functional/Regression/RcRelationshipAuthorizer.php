<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional\Regression;

use AlexFigures\Symfony\Http\Authorization\RelationshipAuthorizerInterface;
use AlexFigures\Symfony\Http\Authorization\RelationshipOperation;
use Symfony\Component\HttpFoundation\Request;

final class RcRelationshipAuthorizer implements RelationshipAuthorizerInterface
{
    public function isGranted(Request $request, string $type, string $id, string $relationship, RelationshipOperation $operation): bool
    {
        return $type === 'rc-tagged' && $id === 'stored' && in_array($relationship, ['one', 'many'], true)
            && $request->headers->get('X-Relationship-Permission') === $operation->value;
    }
}
