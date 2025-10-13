<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Util;

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

    /**
     * @return list<array<string, mixed>>
     */
    protected function assertErrors(Response $response, int $status): array
    {
        $decoded = $this->decode($response);

        Assert::assertSame($status, $response->getStatusCode());
        Assert::assertArrayHasKey('errors', $decoded, 'JSON:API error document must contain an errors member.');
        Assert::assertIsArray($decoded['errors']);

        /** @var list<array<string, mixed>> $errors */
        $errors = $decoded['errors'];

        return $errors;
    }

    /**
     * @param array<string, mixed> $error
     */
    protected function assertErrorPointer(array $error, string $pointer): void
    {
        Assert::assertArrayHasKey('source', $error, 'Error must contain a source member.');
        Assert::assertIsArray($error['source']);
        Assert::assertSame($pointer, $error['source']['pointer'] ?? null);
    }

    /**
     * @param array<string, mixed> $error
     */
    protected function assertErrorParameter(array $error, string $parameter): void
    {
        Assert::assertArrayHasKey('source', $error);
        Assert::assertIsArray($error['source']);
        Assert::assertSame($parameter, $error['source']['parameter'] ?? null);
    }

    /**
     * @param array<string, mixed> $error
     */
    protected function assertErrorHeader(array $error, string $header): void
    {
        Assert::assertArrayHasKey('source', $error);
        Assert::assertIsArray($error['source']);
        Assert::assertSame($header, $error['source']['header'] ?? null);
    }
}
