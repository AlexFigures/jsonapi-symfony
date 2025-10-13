<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile\Hook;

use AlexFigures\Symfony\Contract\Data\ChangeSet;
use AlexFigures\Symfony\Profile\ProfileContext;

interface WriteHook
{
    public function onBeforeCreate(ProfileContext $context, string $type, ChangeSet $changeSet): void;

    public function onBeforeUpdate(ProfileContext $context, string $type, string $id, ChangeSet $changeSet): void;

    public function onBeforeDelete(ProfileContext $context, string $type, string $id): void;
}
