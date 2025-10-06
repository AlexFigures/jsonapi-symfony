<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Profile\Hook;

use JsonApi\Symfony\Http\Relationship\ResourceIdentifier;
use JsonApi\Symfony\Profile\ProfileContext;

interface RelationshipHook
{
    /**
     * @param list<ResourceIdentifier> $targets
     */
    public function onBeforeRelReplaceToMany(ProfileContext $context, string $type, string $id, string $relationship, array $targets): void;

    public function onBeforeRelReplaceToOne(ProfileContext $context, string $type, string $id, string $relationship, ?ResourceIdentifier $target): void;

    /**
     * @param list<ResourceIdentifier> $targets
     */
    public function onBeforeRelAddToMany(ProfileContext $context, string $type, string $id, string $relationship, array $targets): void;

    /**
     * @param list<ResourceIdentifier> $targets
     */
    public function onBeforeRelRemoveFromToMany(ProfileContext $context, string $type, string $id, string $relationship, array $targets): void;
}
