<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Cache;

use Symfony\Component\HttpFoundation\Request;

final class CacheKeyBuilder
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $etag = $config['etag'] ?? [];
        $this->includeQueryShape = (bool) ($etag['include_query_shape'] ?? true);
    }

    private bool $includeQueryShape;

    public function build(Request $request): string
    {
        $parts = [
            $request->attributes->get('_route', 'unknown'),
            $request->attributes->get('type', ''),
            (string) $request->attributes->get('id', ''),
            (string) $request->attributes->get('relationship', ''),
        ];

        if ($this->includeQueryShape) {
            $parts[] = $this->normalizeQuery($request);
            $parts[] = $this->normalizeHeader($request->headers->get('Accept'));
            $parts[] = $this->normalizeHeader($request->headers->get('Accept-Language'));
        }

        return implode('|', array_map(static fn ($value): string => (string) $value, $parts));
    }

    private function normalizeQuery(Request $request): string
    {
        $query = $request->query->all();
        if ($query === []) {
            return '';
        }

        ksort($query);

        return http_build_query($query);
    }

    private function normalizeHeader(?string $header): string
    {
        if ($header === null) {
            return '';
        }

        return strtolower(trim($header));
    }
}
