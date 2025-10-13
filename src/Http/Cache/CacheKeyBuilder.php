<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Cache;

use Symfony\Component\HttpFoundation\Request;

/**
 * @phpstan-type CacheKeyConfig array{
 *     etag?: array{include_query_shape?: bool}
 * }
 */
final class CacheKeyBuilder
{
    /**
     * @param CacheKeyConfig $config
     */
    public function __construct(array $config = [])
    {
        /** @var array{include_query_shape?: bool} $etag */
        $etag = $config['etag'] ?? [];
        $this->includeQueryShape = (bool) ($etag['include_query_shape'] ?? true);
    }

    private bool $includeQueryShape;

    public function build(Request $request): string
    {
        $route = $request->attributes->get('_route');
        $type = $request->attributes->get('type');
        $id = $request->attributes->get('id');
        $relationship = $request->attributes->get('relationship');

        $parts = [
            is_string($route) && $route !== '' ? $route : 'unknown',
            is_string($type) ? $type : '',
            is_scalar($id) ? (string) $id : '',
            is_scalar($relationship) ? (string) $relationship : '',
        ];

        if ($this->includeQueryShape) {
            $parts[] = $this->normalizeQuery($request);
            $parts[] = $this->normalizeHeader($request->headers->get('Accept'));
            $parts[] = $this->normalizeHeader($request->headers->get('Accept-Language'));
        }

        return implode('|', $parts);
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
