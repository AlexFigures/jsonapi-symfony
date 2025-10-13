<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Cache;

use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;

/**
 * @phpstan-type HeadersConfig array{
 *     headers?: array{
 *         public?: bool,
 *         max_age?: int,
 *         s_maxage?: int,
 *         stale_while_revalidate?: int,
 *         stale_if_error?: int,
 *         add_age?: bool
 *     },
 *     vary?: array{
 *         accept?: bool,
 *         accept_language?: bool
 *     },
 *     surrogate_keys?: array{
 *         enabled?: bool,
 *         header_name?: string
 *     }
 * }
 */
final class HeadersApplier
{
    /**
     * @param HeadersConfig $config
     */
    public function __construct(array $config)
    {
        /** @var array{public?: bool, max_age?: int, s_maxage?: int, stale_while_revalidate?: int, stale_if_error?: int, add_age?: bool} $headers */
        $headers = $config['headers'] ?? [];
        /** @var array{accept?: bool, accept_language?: bool} $vary */
        $vary = $config['vary'] ?? [];
        /** @var array{enabled?: bool, header_name?: string} $surrogate */
        $surrogate = $config['surrogate_keys'] ?? [];

        $this->public = (bool) ($headers['public'] ?? true);
        $this->maxAge = (int) ($headers['max_age'] ?? 0);
        $this->sharedMaxAge = (int) ($headers['s_maxage'] ?? 0);
        $this->staleWhileRevalidate = (int) ($headers['stale_while_revalidate'] ?? 0);
        $this->staleIfError = (int) ($headers['stale_if_error'] ?? 0);
        $this->addAge = (bool) ($headers['add_age'] ?? false);
        $this->varyAccept = (bool) ($vary['accept'] ?? true);
        $this->varyLanguage = (bool) ($vary['accept_language'] ?? false);
        $this->surrogateEnabled = (bool) ($surrogate['enabled'] ?? false);
        $this->surrogateHeader = (string) ($surrogate['header_name'] ?? 'Surrogate-Key');
    }

    private bool $public;

    private int $maxAge;

    private int $sharedMaxAge;

    private int $staleWhileRevalidate;

    private int $staleIfError;

    private bool $addAge;

    private bool $varyAccept;

    private bool $varyLanguage;

    private bool $surrogateEnabled;

    private string $surrogateHeader;

    /**
     * @param list<string> $surrogateKeys
     */
    public function apply(Response $response, ?string $etag, ?DateTimeImmutable $lastModified, array $surrogateKeys = [], bool $weak = false): void
    {
        if ($etag !== null) {
            $response->setEtag($etag, $weak);
        }

        if ($lastModified !== null) {
            $response->setLastModified($lastModified);
        }

        $cacheControl = [];
        $cacheControl[] = $this->public ? 'public' : 'private';
        if ($this->maxAge > 0) {
            $cacheControl[] = sprintf('max-age=%d', $this->maxAge);
        }

        if ($this->sharedMaxAge > 0) {
            $cacheControl[] = sprintf('s-maxage=%d', $this->sharedMaxAge);
        }

        if ($this->staleWhileRevalidate > 0) {
            $cacheControl[] = sprintf('stale-while-revalidate=%d', $this->staleWhileRevalidate);
        }

        if ($this->staleIfError > 0) {
            $cacheControl[] = sprintf('stale-if-error=%d', $this->staleIfError);
        }

        $response->headers->set('Cache-Control', implode(', ', $cacheControl));

        $vary = [];
        if ($this->varyAccept) {
            $vary[] = 'Accept';
        }

        if ($this->varyLanguage) {
            $vary[] = 'Accept-Language';
        }

        if ($vary !== []) {
            $response->headers->set('Vary', implode(', ', $vary));
        }

        if ($this->addAge) {
            $response->headers->set('Age', '0');
        }

        if ($this->surrogateEnabled && $surrogateKeys !== []) {
            $response->headers->set($this->surrogateHeader, implode(' ', $surrogateKeys));
        }
    }
}
