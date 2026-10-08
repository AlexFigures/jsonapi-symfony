<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Profile\Hook;

use AlexFigures\JsonApi\Contract\Data\ChangeSet;
use AlexFigures\JsonApi\Profile\ProfileContext;

/** @api */
interface WriteHook
{
    public function onBeforeCreate(ProfileContext $context, string $type, ChangeSet $changeSet): void;

    public function onBeforeUpdate(ProfileContext $context, string $type, string $id, ChangeSet $changeSet): void;

    public function onBeforeDelete(ProfileContext $context, string $type, string $id): void;
}
