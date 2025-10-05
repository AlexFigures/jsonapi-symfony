<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Util;

use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\JsonResponse;

trait JsonApiResponseAsserts
{
    /**
     * @return array<string, mixed>
     */
    protected function decode(JsonResponse $response): array
    {
        Assert::assertSame('application/vnd.api+json', $response->headers->get('Content-Type'));

        return json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
