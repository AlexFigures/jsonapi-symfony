<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Profile\Hook;

use AlexFigures\JsonApi\Profile\ProfileContext;
use AlexFigures\JsonApi\Query\Criteria;
use Symfony\Component\HttpFoundation\Request;

/** @api */
interface QueryHook
{
    public function onParseQuery(ProfileContext $context, Request $request, Criteria $criteria): void;
}
