<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Util;

use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

trait JsonApiResponseAsserts
{
    /**
     * @return array<string, mixed>
     */
    protected function decode(Response $response): array
    {
        Assert::assertInstanceOf(JsonResponse::class, $response);
        Assert::assertSame('application/vnd.api+json', $response->headers->get('Content-Type'));

        /** @var JsonResponse $jsonResponse */
        $jsonResponse = $response;

        $decoded = json_decode((string) $jsonResponse->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            Assert::fail('Expected JSON:API document to decode to an array.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
