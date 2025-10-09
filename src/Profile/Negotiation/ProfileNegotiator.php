<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Profile\Negotiation;

use JsonApi\Symfony\Http\Exception\NotAcceptableException;
use JsonApi\Symfony\Profile\ProfileContext;
use JsonApi\Symfony\Profile\ProfileInterface;
use JsonApi\Symfony\Profile\ProfileRegistry;
use Symfony\Component\HttpFoundation\Request;

final class ProfileNegotiator
{
    /** @var list<string> */
    private array $enabledByDefault;

    /** @var array<string, list<string>> */
    private array $perType;

    private bool $requireKnownProfiles;

    private bool $echoProfilesInContentType;

    private bool $emitLinkHeader;

    /**
     * @param list<string>                                                                                   $enabledByDefault
     * @param array<string, list<string>>                                                                    $perType
     * @param array{require_known_profiles?: bool, echo_profiles_in_content_type?: bool, link_header?: bool} $negotiation
     */
    public function __construct(
        private readonly ProfileRegistry $registry,
        array $enabledByDefault = [],
        array $perType = [],
        array $negotiation = []
    ) {
        $normalizedDefault = [];
        foreach ($enabledByDefault as $uri) {
            if ($uri !== '') {
                $normalizedDefault[] = $uri;
            }
        }
        $this->enabledByDefault = array_values(array_unique($normalizedDefault));

        $normalizedPerType = [];
        foreach ($perType as $type => $uris) {
            $normalizedUris = [];
            foreach ($uris as $uri) {
                if ($uri !== '') {
                    $normalizedUris[] = $uri;
                }
            }

            $normalizedPerType[$type] = $normalizedUris;
        }
        $this->perType = $normalizedPerType;

        $this->requireKnownProfiles = (bool) ($negotiation['require_known_profiles'] ?? false);
        $this->echoProfilesInContentType = (bool) ($negotiation['echo_profiles_in_content_type'] ?? true);
        $this->emitLinkHeader = (bool) ($negotiation['link_header'] ?? true);
    }

    public function negotiate(Request $request): ProfileContext
    {
        $sources = [];
        /** @var list<string> $activeUris */
        $activeUris = [];

        foreach ($this->enabledByDefault as $uri) {
            $this->appendUri($activeUris, $uri);
        }
        if ($activeUris !== []) {
            $sources['default'] = $activeUris;
        }

        $contentProfiles = $this->extractProfiles($request->headers->get('Content-Type'));
        if ($contentProfiles !== []) {
            $resolved = $this->filterKnown($contentProfiles, $request->headers->get('Content-Type'));
            foreach ($resolved as $uri) {
                $this->appendUri($activeUris, $uri);
            }
            if ($resolved !== []) {
                $sources['content-type'] = $resolved;
            }
        }

        $acceptHeader = $request->headers->get('Accept');
        $acceptProfiles = $this->extractProfiles($acceptHeader);
        /** @var list<string> $disabled */
        $disabled = [];
        /** @var list<string> $resolvedAccept */
        $resolvedAccept = [];
        if ($acceptProfiles !== []) {
            foreach ($acceptProfiles as $uri) {
                if ($uri === '') {
                    continue;
                }
                if ($uri[0] === '!') {
                    $disabled[] = substr($uri, 1);
                    continue;
                }
                $resolvedAccept[] = $uri;
            }

            $resolvedAccept = $this->filterKnown($resolvedAccept, $acceptHeader);
            foreach ($resolvedAccept as $uri) {
                $this->appendUri($activeUris, $uri);
            }
            if ($resolvedAccept !== []) {
                $sources['accept'] = $resolvedAccept;
            }
        }

        if ($disabled !== []) {
            foreach ($disabled as $uri) {
                $index = array_search($uri, $activeUris, true);
                if ($index !== false) {
                    unset($activeUris[$index]);
                }
            }
            /** @var list<string> $activeUris */
            $activeUris = array_values($activeUris);
            $sources['disabled'] = $disabled;
        }

        $profiles = $this->resolveProfiles($activeUris);
        $perTypeProfiles = [];
        foreach ($this->perType as $type => $uris) {
            $resolved = $this->resolveProfiles($uris);
            if ($resolved !== []) {
                /** @var list<ProfileInterface> $profilesForType */
                $profilesForType = array_values($resolved);
                $perTypeProfiles[$type] = $profilesForType;
            }
        }

        return new ProfileContext($profiles, $perTypeProfiles, $sources);
    }

    public function shouldEchoProfilesInContentType(): bool
    {
        return $this->echoProfilesInContentType;
    }

    public function shouldEmitLinkHeader(): bool
    {
        return $this->emitLinkHeader;
    }

    /**
     * @return list<string>
     */
    private function extractProfiles(?string $header): array
    {
        if ($header === null) {
            return [];
        }

        $profiles = [];
        $parts = preg_split('/,(?![^\"]*\")/', $header) ?: [$header];
        foreach ($parts as $part) {
            if (!preg_match('/profile\s*=\s*([^;]+)/i', $part, $matches)) {
                continue;
            }

            $raw = trim($matches[1]);
            $raw = trim($raw, "'\"");
            if ($raw === '') {
                continue;
            }

            foreach (preg_split('/\s+/', $raw) ?: [] as $uri) {
                $uri = trim($uri);
                if ($uri !== '') {
                    $profiles[] = $uri;
                }
            }
        }

        return $profiles;
    }

    /**
     * @param list<string> $uris
     *
     * @return list<string>
     */
    private function filterKnown(array $uris, ?string $headerValue): array
    {
        if ($uris === []) {
            return [];
        }

        $unknown = [];
        $known = [];

        foreach ($uris as $uri) {
            if ($this->registry->has($uri)) {
                $known[] = $uri;
                continue;
            }

            $unknown[] = $uri;
        }

        if ($unknown !== [] && $this->requireKnownProfiles) {
            $message = sprintf('Unknown profile(s) requested: %s', implode(', ', $unknown));
            throw new NotAcceptableException($headerValue, $message);
        }

        return $known;
    }

    /**
     * @param list<string> $uris
     */
    private function appendUri(array &$uris, string $uri): void
    {
        if (!in_array($uri, $uris, true)) {
            $uris[] = $uri;
        }
    }

    /**
     * @param list<string> $uris
     *
     * @return array<string, ProfileInterface>
     */
    private function resolveProfiles(array $uris): array
    {
        $resolved = [];
        foreach ($uris as $uri) {
            $profile = $this->registry->get($uri);
            if ($profile instanceof ProfileInterface) {
                $resolved[$profile->uri()] = $profile;
            }
        }

        return $resolved;
    }
}
