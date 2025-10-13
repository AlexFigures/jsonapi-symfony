<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile\Hook;

use AlexFigures\Symfony\Profile\ProfileContext;
use AlexFigures\Symfony\Query\Criteria;

interface ReadHook
{
    public function onBeforeFindCollection(ProfileContext $context, string $type, Criteria $criteria): void;

    public function onBeforeFindOne(ProfileContext $context, string $type, string $id, Criteria $criteria): void;
}
