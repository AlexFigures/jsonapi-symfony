<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Controller\Support;

use Symfony\Component\HttpFoundation\JsonResponse;

/** @internal Retains the selected representation when a HEAD body is suppressed. */
final class RepresentationResponse extends JsonResponse
{
    public ?string $representationContent = null;
}
