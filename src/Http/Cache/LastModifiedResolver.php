<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Cache;

use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class LastModifiedResolver
{
    public function resolve(Request $request, Response $response): DateTimeImmutable
    {
        $header = $response->headers->get('Last-Modified');
        if ($header !== null) {
            $time = strtotime($header);
            if ($time !== false) {
                return new DateTimeImmutable('@' . $time);
            }
        }

        return new DateTimeImmutable();
    }
}
