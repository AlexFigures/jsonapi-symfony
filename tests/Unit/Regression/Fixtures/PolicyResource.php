<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Regression\Fixtures;

use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use AlexFigures\Symfony\Resource\Attribute\Relationship;
use AlexFigures\Symfony\Resource\Metadata\RelationshipLinkingPolicy;

#[JsonApiResource(type: 'policies', relationshipPolicies: ['inherited' => RelationshipLinkingPolicy::VERIFY, 'explicit' => RelationshipLinkingPolicy::VERIFY])]
final class PolicyResource
{
    #[Relationship(targetType: 'primary')]
    public ?PrimaryResource $inherited = null;
    #[Relationship(targetType: 'primary', linkingPolicy: RelationshipLinkingPolicy::REFERENCE)]
    public ?PrimaryResource $explicit = null;
}
