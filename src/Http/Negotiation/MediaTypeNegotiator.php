<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Negotiation;

use AlexFigures\Symfony\Atomic\AtomicConfig;
use AlexFigures\Symfony\Http\Exception\NotAcceptableException;
use AlexFigures\Symfony\Http\Exception\UnsupportedMediaTypeException;
use AlexFigures\Symfony\Http\Negotiation\MediaTypePolicyProviderInterface;
use Symfony\Component\HttpFoundation\Request;

final class MediaTypeNegotiator
{
    public function __construct(
        private readonly AtomicConfig $config,
        private readonly MediaTypePolicyProviderInterface $policyProvider,
    ) {
    }

    public function assertAtomicExt(Request $request): void
    {
        if (!$this->config->requireExtHeader) {
            return;
        }

        $policy = $this->policyProvider->getPolicy($request);

        $contentType = $request->headers->get('Content-Type');
        $candidates = ParsedMediaType::parse($contentType ?? '');
        $media = count($candidates) === 1 ? $candidates[0] : null;
        $atomic = 'https://jsonapi.org/ext/atomic';
        if ($media === null || !$media->validJsonApi([$atomic]) || !in_array($atomic, $media->extensions(), true)
            || (!$policy->allowsAnyRequestType() && !in_array($media->name, $policy->allowedRequestTypes, true))) {
            throw new UnsupportedMediaTypeException($contentType, 'Atomic operations require the JSON:API atomic extension.');
        }
        $accept = $request->headers->get('Accept');
        if ($accept === null) {
            return;
        }
        foreach (ParsedMediaType::parse($accept, true) as $candidate) {
            if ($candidate->quality > 0 && ($candidate->name === '*/*'
                || ($candidate->validJsonApi([$atomic]) && in_array($atomic, $candidate->extensions(), true)))) {
                return;
            }
        }
        throw new NotAcceptableException($accept, 'No acceptable Atomic representation is available.');
    }
}
