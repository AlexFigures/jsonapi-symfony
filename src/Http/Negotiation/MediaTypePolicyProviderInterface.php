<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Negotiation;

use Symfony\Component\HttpFoundation\Request;

/** @api */
interface MediaTypePolicyProviderInterface
{
    public function getPolicy(Request $request): MediaTypePolicy;
}
