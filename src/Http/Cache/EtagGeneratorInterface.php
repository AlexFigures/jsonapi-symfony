<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Cache;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** @api */
interface EtagGeneratorInterface
{
    public function generate(Request $request, Response $response, string $cacheKey, bool $weak): ?string;
}
