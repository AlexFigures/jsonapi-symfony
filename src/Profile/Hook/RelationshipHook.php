<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile\Hook;

use AlexFigures\Symfony\Contract\Data\ResourceIdentifier;
use AlexFigures\Symfony\Profile\ProfileContext;

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
