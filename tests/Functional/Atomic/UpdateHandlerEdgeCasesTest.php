<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional\Atomic;

use JsonApi\Symfony\Http\Exception\BadRequestException;
use JsonApi\Symfony\Http\Negotiation\MediaType;
use JsonApi\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests edge cases for UpdateHandler to kill escaped mutants.
 *
 * Targets escaped mutants in src/Atomic/Execution/Handlers/UpdateHandler.php:
 * - Type extraction logic (lines 38-41)
 * - Empty string validation
 * - ID handling
 */
final class UpdateHandlerEdgeCasesTest extends JsonApiTestCase
{
    /**
     * Test that type can be extracted from data when using href (ref is null).
     * Kills mutant: UpdateHandler.php:38 (NullSafePropertyCall - $operation->ref?->type)
     * Kills mutant: UpdateHandler.php:39 (Identical - $type === null check)
     */
    public function testUpdateOperationWithHrefUsesDataType(): void
    {
        $controller = $this->atomicController();

        // First create an author
        $createPayload = [
            'atomic:operations' => [
                [
                    'op' => 'add',
                    'ref' => ['type' => 'authors'],
                    'data' => [
                        'type' => 'authors',
                        'attributes' => [
                            'name' => 'Original Name',
                        ],
                    ],
                ],
            ],
        ];

        $createRequest = Request::create('/api/operations', 'POST', server: [
            'CONTENT_TYPE' => MediaType::JSON_API_ATOMIC,
            'HTTP_ACCEPT' => MediaType::JSON_API_ATOMIC,
        ], content: json_encode($createPayload, \JSON_THROW_ON_ERROR));

        $createResponse = $controller($createRequest);
        $createResult = json_decode($createResponse->getContent(), true);
        $authorId = $createResult['atomic:results'][0]['data']['id'];

        // Now update using href instead of ref
        $updatePayload = [
            'atomic:operations' => [
                [
                    'op' => 'update',
                    'href' => '/api/authors/' . $authorId,  // Using href instead of ref
                    'data' => [
                        'type' => 'authors',  // Type from data
                        'id' => $authorId,
                        'attributes' => [
                            'name' => 'Updated Name',
                        ],
                    ],
                ],
            ],
        ];

        $updateRequest = Request::create('/api/operations', 'POST', server: [
            'CONTENT_TYPE' => MediaType::JSON_API_ATOMIC,
            'HTTP_ACCEPT' => MediaType::JSON_API_ATOMIC,
        ], content: json_encode($updatePayload, \JSON_THROW_ON_ERROR));

        $updateResponse = $controller($updateRequest);

        self::assertSame(200, $updateResponse->getStatusCode());
        $updateResult = json_decode($updateResponse->getContent(), true);
        self::assertSame('authors', $updateResult['atomic:results'][0]['data']['type']);
        self::assertSame('Updated Name', $updateResult['atomic:results'][0]['data']['attributes']['name']);
    }

    /**
     * Test that empty string in data.type is rejected.
     * Kills mutant: UpdateHandler.php:39 (NotIdentical - $data['type'] !== '' check)
     */
    public function testUpdateOperationWithEmptyStringTypeThrowsError(): void
    {
        $controller = $this->atomicController();

        $payload = [
            'atomic:operations' => [
                [
                    'op' => 'update',
                    'ref' => ['type' => 'authors', 'id' => 'some-id'],
                    'data' => [
                        'type' => '',  // Empty string should be rejected
                        'id' => 'some-id',
                        'attributes' => [
                            'name' => 'Test',
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

    /**
     * Test that non-string type in data is rejected.
     * Kills mutant: UpdateHandler.php:39 (LogicalAndSingleSubExprNegation - !is_string check)
     */
    public function testUpdateOperationWithNonStringTypeThrowsError(): void
    {
        $controller = $this->atomicController();

        $payload = [
            'atomic:operations' => [
                [
                    'op' => 'update',
                    'ref' => ['type' => 'authors', 'id' => 'some-id'],
                    'data' => [
                        'type' => 123,  // Non-string type should be rejected
                        'id' => 'some-id',
                        'attributes' => [
                            'name' => 'Test',
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

    /**
     * Test update operation with valid ref and data.
     * Ensures the happy path works correctly.
     */
    public function testUpdateOperationWithValidRefAndData(): void
    {
        $controller = $this->atomicController();

        // First create an author
        $createPayload = [
            'atomic:operations' => [
                [
                    'op' => 'add',
                    'ref' => ['type' => 'authors'],
                    'data' => [
                        'type' => 'authors',
                        'attributes' => [
                            'name' => 'Original Name',
                        ],
                    ],
                ],
            ],
        ];

        $createRequest = Request::create('/api/operations', 'POST', server: [
            'CONTENT_TYPE' => MediaType::JSON_API_ATOMIC,
            'HTTP_ACCEPT' => MediaType::JSON_API_ATOMIC,
        ], content: json_encode($createPayload, \JSON_THROW_ON_ERROR));

        $createResponse = $controller($createRequest);
        $createResult = json_decode($createResponse->getContent(), true);
        $authorId = $createResult['atomic:results'][0]['data']['id'];

        // Now update with both ref.type and data.type
        $updatePayload = [
            'atomic:operations' => [
                [
                    'op' => 'update',
                    'ref' => ['type' => 'authors', 'id' => $authorId],
                    'data' => [
                        'type' => 'authors',
                        'id' => $authorId,
                        'attributes' => [
                            'name' => 'Updated Name',
                        ],
                    ],
                ],
            ],
        ];

        $updateRequest = Request::create('/api/operations', 'POST', server: [
            'CONTENT_TYPE' => MediaType::JSON_API_ATOMIC,
            'HTTP_ACCEPT' => MediaType::JSON_API_ATOMIC,
        ], content: json_encode($updatePayload, \JSON_THROW_ON_ERROR));

        $updateResponse = $controller($updateRequest);

        self::assertSame(200, $updateResponse->getStatusCode());
        $updateResult = json_decode($updateResponse->getContent(), true);
        self::assertSame('authors', $updateResult['atomic:results'][0]['data']['type']);
        self::assertSame($authorId, $updateResult['atomic:results'][0]['data']['id']);
        self::assertSame('Updated Name', $updateResult['atomic:results'][0]['data']['attributes']['name']);
    }

    /**
     * Test that update operation fails when type cannot be determined.
     * Kills mutants related to type resolution logic.
     */
    public function testUpdateOperationWithoutTypeThrowsError(): void
    {
        $controller = $this->atomicController();

        $payload = [
            'atomic:operations' => [
                [
                    'op' => 'update',
                    'ref' => ['id' => 'some-id'],  // No type in ref
                    'data' => [
                        // No type in data either
                        'id' => 'some-id',
                        'attributes' => [
                            'name' => 'Test',
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
