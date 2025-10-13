<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Cache;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @phpstan-type HashEtagConfig array{
 *     etag?: array{hash_algo?: string},
 *     hash_algo?: string
 * }
 */
final class HashEtagGenerator implements EtagGeneratorInterface
{
    /**
     * @param HashEtagConfig $config
     */
    public function __construct(array $config = [])
    {
        $source = $config['etag'] ?? $config;
        $hashAlgo = $source['hash_algo'] ?? null;
        $algorithm = is_string($hashAlgo) && $hashAlgo !== '' ? $hashAlgo : 'xxh3';
        if (!in_array($algorithm, hash_algos(), true)) {
            $algorithm = 'sha256';
        }

        $this->algorithm = $algorithm;
    }

    private string $algorithm;

    public function generate(Request $request, Response $response, string $cacheKey, bool $weak): ?string
    {
        $content = $response->getContent();
        if ($content === false) {
            return null;
        }

        $payload = $cacheKey . '\n' . $content;
        return hash($this->algorithm, $payload, false);
    }
}
