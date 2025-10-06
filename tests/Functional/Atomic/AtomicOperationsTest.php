<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional\Atomic;

use JsonApi\Symfony\Http\Negotiation\MediaType;
use JsonApi\Symfony\Http\Exception\UnsupportedMediaTypeException;
use JsonApi\Symfony\Http\Exception\BadRequestException;
use JsonApi\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;

final class AtomicOperationsTest extends JsonApiTestCase
{
    public function testAddResourceOperationReturnsResult(): void
    {
        $controller = $this->atomicController();

        $payload = [
            'atomic:operations' => [
                [
                    'op' => 'add',
                    'ref' => ['type' => 'authors'],
                    'data' => [
                        'type' => 'authors',
                        'attributes' => [
                            'name' => 'New Atomic Author',
                        ],
                    ],
                ],
            ],
        ];

        $request = Request::create('/api/operations', 'POST', server: [
            'CONTENT_TYPE' => MediaType::JSON_API_ATOMIC,
            'HTTP_ACCEPT' => MediaType::JSON_API_ATOMIC,
        ], content: json_encode($payload, \JSON_THROW_ON_ERROR));

        $response = $controller($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(MediaType::JSON_API_ATOMIC, $response->headers->get('Content-Type'));

        $decoded = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('atomic:results', $decoded);
        self::assertCount(1, $decoded['atomic:results']);
        self::assertArrayHasKey('data', $decoded['atomic:results'][0]);
        self::assertSame('authors', $decoded['atomic:results'][0]['data']['type']);
        self::assertNotEmpty($decoded['atomic:results'][0]['data']['id']);
    }

    public function testMissingAtomicExtensionInContentTypeTriggers415(): void
    {
        $controller = $this->atomicController();

        $payload = [
            'atomic:operations' => [
                [
                    'op' => 'add',
                    'ref' => ['type' => 'authors'],
                    'data' => [
                        'type' => 'authors',
                        'attributes' => ['name' => 'Author'],
                    ],
                ],
            ],
        ];

        $request = Request::create('/api/operations', 'POST', server: [
            'CONTENT_TYPE' => MediaType::JSON_API,
            'HTTP_ACCEPT' => MediaType::JSON_API_ATOMIC,
        ], content: json_encode($payload, \JSON_THROW_ON_ERROR));

        $this->expectException(UnsupportedMediaTypeException::class);
        $controller($request);
    }

    public function testUnknownOperationCodeTriggersBadRequest(): void
    {
        $controller = $this->atomicController();

        $payload = [
            'atomic:operations' => [
                [
                    'op' => 'merge',
                    'ref' => ['type' => 'authors'],
                ],
            ],
        ];

        $request = Request::create('/api/operations', 'POST', server: [
            'CONTENT_TYPE' => MediaType::JSON_API_ATOMIC,
            'HTTP_ACCEPT' => MediaType::JSON_API_ATOMIC,
        ], content: json_encode($payload, \JSON_THROW_ON_ERROR));

        $this->expectException(BadRequestException::class);
        $controller($request);
    }
}
