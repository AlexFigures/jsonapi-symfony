<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Negotiation;

final class MediaType
{
    public const JSON_API = 'application/vnd.api+json';

    public const JSON_API_ATOMIC = 'application/vnd.api+json;ext="https://jsonapi.org/ext/atomic"';

    private function __construct()
    {
    }
}
