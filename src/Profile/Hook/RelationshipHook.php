<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Profile\Hook;

use AlexFigures\JsonApi\Contract\Data\ResourceIdentifier;
use AlexFigures\JsonApi\Profile\ProfileContext;

/** @api */
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
