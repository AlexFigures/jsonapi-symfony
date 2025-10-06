<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Cache;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class HashEtagGenerator implements EtagGeneratorInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $source = $config['etag'] ?? $config;
        $this->algorithm = (string) ($source['hash_algo'] ?? 'xxh3');
    }

    private string $algorithm;

    public function generate(Request $request, Response $response, string $cacheKey, bool $weak): ?string
    {
        $content = $response->getContent();
        if ($content === false) {
            $content = '';
        }

        $payload = $cacheKey . '\n' . $content;
        $hash = hash($this->algorithm, $payload, false);
        if ($hash === false) {
            return null;
        }

        if ($weak) {
            return sprintf('W/"%s"', $hash);
        }

        return sprintf('"%s"', $hash);
    }
}
