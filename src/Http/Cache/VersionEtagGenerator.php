<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Cache;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** @internal */
final readonly class VersionEtagGenerator implements EtagGeneratorInterface
{
    public function __construct(
        private string $headerName = 'X-Resource-Version',
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

        return $normalized;
    }
}
