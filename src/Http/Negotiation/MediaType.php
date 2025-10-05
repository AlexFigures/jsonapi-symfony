<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Negotiation;

final class MediaType
{
    public const JSON_API = 'application/vnd.api+json';

    private function __construct()
    {
    }
}
