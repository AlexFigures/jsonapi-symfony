<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Profile\Hook;

use JsonApi\Symfony\Profile\ProfileContext;
use JsonApi\Symfony\Query\Criteria;
use Symfony\Component\HttpFoundation\Request;

interface QueryHook
{
    public function onParseQuery(ProfileContext $context, Request $request, Criteria $criteria): void;
}
