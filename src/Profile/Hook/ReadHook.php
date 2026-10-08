<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Profile\Hook;

use AlexFigures\JsonApi\Profile\ProfileContext;
use AlexFigures\JsonApi\Query\Criteria;

/** @api */
interface ReadHook
{
    public function onBeforeFindCollection(ProfileContext $context, string $type, Criteria $criteria): void;

    public function onBeforeFindOne(ProfileContext $context, string $type, string $id, Criteria $criteria): void;
}
