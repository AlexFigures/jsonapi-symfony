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
        if (!is_array($decoded)) {
            self::fail('Response payload must decode to an array.');
        }
        /** @var array<string, mixed> $decoded */
        self::assertArrayHasKey('atomic:results', $decoded);
        $results = $decoded['atomic:results'];
        if (!is_array($results) || !array_is_list($results)) {
            self::fail('Atomic results must be represented as a list.');
        }
        /** @var list<array<string, mixed>> $results */
        self::assertCount(1, $results);
        $firstResult = $results[0];
        self::assertArrayHasKey('data', $firstResult);
        $data = $firstResult['data'];
        if (!is_array($data)) {
            self::fail('Atomic result data must be an object.');
        }
        self::assertSame('authors', $data['type']);
        self::assertArrayHasKey('id', $data);
        self::assertNotEmpty($data['id']);
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

    public function testUnknownResourceTypeTriggersBadRequest(): void
    {
        $controller = $this->atomicController();

        $payload = [
            'atomic:operations' => [
                [
                    'op' => 'add',
                    'ref' => ['type' => 'aliens'],
                    'data' => [
                        'type' => 'aliens',
                        'attributes' => [
                            'name' => 'Xenomorph',
                        ],
                    ],
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
