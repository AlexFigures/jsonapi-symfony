<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Negotiation;

use Symfony\Component\HttpFoundation\Request;

interface MediaTypePolicyProviderInterface
{
    public function getPolicy(Request $request): MediaTypePolicy;
}
