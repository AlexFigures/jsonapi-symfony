<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Profile\Hook;

use AlexFigures\Symfony\Profile\ProfileContext;
use AlexFigures\Symfony\Query\Criteria;
use Symfony\Component\HttpFoundation\Request;

interface QueryHook
{
    public function onParseQuery(ProfileContext $context, Request $request, Criteria $criteria): void;
}
