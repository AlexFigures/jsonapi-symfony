<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Cache;

use DateTimeImmutable;
use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Exception\PreconditionFailedException;
use JsonApi\Symfony\Http\Exception\PreconditionRequiredException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ConditionalRequestEvaluator
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(ErrorMapper $errors, array $config = [])
    {
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

    public function evaluate(Request $request, Response $response, ?string $etag, ?DateTimeImmutable $lastModified): void
    {
        $method = strtoupper($request->getMethod());

        if ($method === 'GET' || $method === 'HEAD') {
            $this->evaluateSafeRequest($request, $response, $etag, $lastModified);

            return;
        }

        if (in_array($method, ['PATCH', 'PUT', 'DELETE', 'POST'], true)) {
            $this->evaluateWriteRequest($request, $etag, $lastModified);
        }
    }

    private function evaluateSafeRequest(Request $request, Response $response, ?string $etag, ?DateTimeImmutable $lastModified): void
    {
        $ifNoneMatch = $request->headers->get('If-None-Match');
        if ($this->enableIfNoneMatch && $etag !== null && $ifNoneMatch !== null) {
            foreach (explode(',', $ifNoneMatch) as $candidate) {
                if (trim($candidate) === $etag) {
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

    private function evaluateWriteRequest(Request $request, ?string $etag, ?DateTimeImmutable $lastModified): void
    {
        $ifMatch = $request->headers->get('If-Match');
        if ($this->requireIfMatchOnWrite && $ifMatch === null) {
            $error = $this->errors->invalidHeader('If-Match', 'If-Match header is required for this request.');

            throw new PreconditionRequiredException([$error]);
        }

        if ($this->enableIfMatch && $etag !== null && $ifMatch !== null) {
            $matched = false;
            foreach (explode(',', $ifMatch) as $candidate) {
                if (trim($candidate) === $etag) {
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                $error = $this->errors->invalidHeader('If-Match', 'The supplied If-Match validator does not match the current resource state.');

                throw new PreconditionFailedException([$error]);
            }
        }

        $ifUnmodifiedSince = $request->headers->get('If-Unmodified-Since');
        if ($this->enableIfUnmodifiedSince && $lastModified !== null && $ifUnmodifiedSince !== null) {
            $date = strtotime($ifUnmodifiedSince);
            if ($date !== false && $lastModified->getTimestamp() > $date) {
                $error = $this->errors->invalidHeader('If-Unmodified-Since', 'The resource has been modified since the provided timestamp.');

                throw new PreconditionFailedException([$error]);
            }
        }
    }
}
