<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Cache;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class VersionEtagGenerator implements EtagGeneratorInterface
{
    public function __construct(
        private readonly string $headerName = 'X-Resource-Version',
    ) {
    }

    public function generate(Request $request, Response $response, string $cacheKey, bool $weak): ?string
    {
        $version = $response->headers->get($this->headerName);
        if ($version === null) {
            return null;
        }

        $normalized = trim($version);
        if ($normalized === '') {
            return null;
        }

        if ($weak) {
            return sprintf('W/"%s"', $normalized);
        }

        return sprintf('"%s"', $normalized);
    }
}
