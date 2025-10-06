<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Profile\Hook;

use JsonApi\Symfony\Profile\ProfileContext;
use JsonApi\Symfony\Query\Criteria;

interface ReadHook
{
    public function onBeforeFindCollection(ProfileContext $context, string $type, Criteria $criteria): void;

    public function onBeforeFindOne(ProfileContext $context, string $type, string $id, Criteria $criteria): void;
}
