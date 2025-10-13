<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Cache;

use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Exception\PreconditionFailedException;
use AlexFigures\Symfony\Http\Exception\PreconditionRequiredException;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @phpstan-type ConditionalConfig array{
 *     conditional?: array{
 *         require_if_match_on_write?: bool,
 *         enable_if_none_match?: bool,
 *         enable_if_modified_since?: bool,
 *         enable_if_match?: bool,
 *         enable_if_unmodified_since?: bool
 *     }
 * }
 */
final class ConditionalRequestEvaluator
{
    /**
     * @param ConditionalConfig $config
     */
    public function __construct(ErrorMapper $errors, array $config = [])
    {
        /** @var array{
         *     require_if_match_on_write?: bool,
         *     enable_if_none_match?: bool,
         *     enable_if_modified_since?: bool,
         *     enable_if_match?: bool,
         *     enable_if_unmodified_since?: bool
         * } $conditional
         */
        $conditional = $config['conditional'] ?? [];

        $this->errors = $errors;
        $this->requireIfMatchOnWrite = (bool) ($conditional['require_if_match_on_write'] ?? false);
        $this->enableIfNoneMatch = (bool) ($conditional['enable_if_none_match'] ?? true);
        $this->enableIfModifiedSince = (bool) ($conditional['enable_if_modified_since'] ?? true);
        $this->enableIfMatch = (bool) ($conditional['enable_if_match'] ?? true);
        $this->enableIfUnmodifiedSince = (bool) ($conditional['enable_if_unmodified_since'] ?? true);
    }

    private ErrorMapper $errors;

    private bool $requireIfMatchOnWrite;

    private bool $enableIfNoneMatch;

    private bool $enableIfModifiedSince;

    private bool $enableIfMatch;

    private bool $enableIfUnmodifiedSince;

    public function evaluate(Request $request, Response $response, ?string $etag, ?DateTimeImmutable $lastModified, bool $weak = false): void
    {
        $method = strtoupper($request->getMethod());

        if ($method === 'GET' || $method === 'HEAD') {
            $this->evaluateSafeRequest($request, $response, $etag, $lastModified);

            return;
        }

        if (in_array($method, ['PATCH', 'PUT', 'DELETE', 'POST'], true)) {
            $this->evaluateWriteRequest($request, $etag, $lastModified, $weak);
        }
    }

    private function evaluateSafeRequest(Request $request, Response $response, ?string $etag, ?DateTimeImmutable $lastModified): void
    {
        $ifNoneMatch = $request->headers->get('If-None-Match');
        if ($this->enableIfNoneMatch && $etag !== null && $ifNoneMatch !== null) {
            $normalizedEtag = $this->normalizeValidator($etag);
            foreach (explode(',', $ifNoneMatch) as $candidate) {
                $candidate = trim($candidate);
                if ($candidate === '') {
                    continue;
                }

                if ($candidate === '*') {
                    $this->setNotModified($response);

                    return;
                }

                $normalizedCandidate = $this->normalizeValidator($candidate);
                if ($normalizedEtag !== '' && $normalizedCandidate !== '' && $normalizedCandidate === $normalizedEtag) {
                    $this->setNotModified($response);

                    return;
                }
            }
        }

        $ifModifiedSince = $request->headers->get('If-Modified-Since');
        if ($this->enableIfModifiedSince && $lastModified !== null && $ifModifiedSince !== null) {
            $date = strtotime($ifModifiedSince);
            if ($date !== false && $lastModified->getTimestamp() <= $date) {
                $this->setNotModified($response);
            }
        }
    }

    private function setNotModified(Response $response): void
    {
        $response->setStatusCode(Response::HTTP_NOT_MODIFIED);
        $response->setContent(null);
        $response->headers->remove('Content-Length');
    }

    private function evaluateWriteRequest(Request $request, ?string $etag, ?DateTimeImmutable $lastModified, bool $weak): void
    {
        $ifMatch = $request->headers->get('If-Match');
        if ($this->requireIfMatchOnWrite && $ifMatch === null) {
            $error = $this->errors->invalidHeader('If-Match', 'If-Match header is required for this request.');

            throw new PreconditionRequiredException([$error]);
        }

        if ($this->enableIfMatch && $etag !== null && $ifMatch !== null) {
            $matched = false;
            $normalizedTarget = $this->normalizeValidator($etag);
            foreach (explode(',', $ifMatch) as $candidate) {
                $candidate = trim($candidate);
                if ($candidate === '') {
                    continue;
                }

                if ($candidate === '*') {
                    $matched = true;
                    break;
                }

                $normalizedCandidate = $this->normalizeValidator($candidate);
                if (
                    $normalizedTarget !== ''
                    && $normalizedCandidate !== ''
                    && $normalizedCandidate === $normalizedTarget
                    && !$weak
                    && !$this->isWeakValidator($candidate)
                ) {
                    $matched = true;
                    break;
                }

                if ($candidate === $etag) {
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                $error = $this->errors->invalidHeader('If-Match', 'The supplied If-Match validator does not match the current resource state.', '412');

                throw new PreconditionFailedException([$error]);
            }
        }

        $ifUnmodifiedSince = $request->headers->get('If-Unmodified-Since');
        if ($this->enableIfUnmodifiedSince && $lastModified !== null && $ifUnmodifiedSince !== null) {
            $date = strtotime($ifUnmodifiedSince);
            if ($date !== false && $lastModified->getTimestamp() > $date) {
                $error = $this->errors->invalidHeader('If-Unmodified-Since', 'The resource has been modified since the provided timestamp.', '412');

                throw new PreconditionFailedException([$error]);
            }
        }
    }

    private function normalizeValidator(string $validator): string
    {
        $trimmed = trim($validator);

        if ($trimmed === '' || $trimmed === '*') {
            return $trimmed;
        }

        if ($this->isWeakValidator($trimmed)) {
            $trimmed = ltrim(substr($trimmed, 2));
        }

        return trim($trimmed, '"');
    }

    private function isWeakValidator(string $validator): bool
    {
        return strncasecmp(trim($validator), 'W/', 2) === 0;
    }
}
