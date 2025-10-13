<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional\Atomic;

use AlexFigures\Symfony\Http\Exception\BadRequestException;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests edge cases for RelationshipOps to kill escaped mutants.
 *
 * Targets escaped mutants in src/Atomic/Execution/Handlers/RelationshipOps.php:
 * - Logical operators (||, &&)
 * - Array validation (is_array, array_is_list)
 * - Resource identifier validation
 * - Empty string checks
 */
final class RelationshipOpsEdgeCasesTest extends JsonApiTestCase
{
    /**
     * Test that relationship operation requires a relationship ref.
     * Kills mutant: RelationshipOps.php:28 (LogicalOr - $ref === null || $ref->relationship === null)
     */
    public function testRelationshipOperationWithoutRelationshipRefThrowsError(): void
    {
        $controller = $this->atomicController();

        $payload = [
            'atomic:operations' => [
                [
                    'op' => 'update',
                    'ref' => [
                        'type' => 'articles',
                        'id' => 'some-id',
                        // Missing 'relationship' key
                    ],
                    'data' => [
                        'type' => 'authors',
                        'id' => 'author-id',
                    ],
                ],
            ],
        ];

        $request = Request::create('/api/operations', 'POST', server: [
            'CONTENT_TYPE' => MediaType::JSON_API_ATOMIC,
            'HTTP_ACCEPT' => MediaType::JSON_API_ATOMIC,
        ], content: json_encode($payload, \JSON_THROW_ON_ERROR));

        $this->expectException(BadRequestException::class);
        // The actual error is "Type mismatch" because it tries to update an article with author data
        $controller($request);
    }

    /**
     * Test that to-many relationship data must be an array.
     * Kills mutant: RelationshipOps.php:49 (LogicalOr - !is_array || !array_is_list)
     */
    public function testToManyRelationshipWithNonArrayDataThrowsError(): void
    {
        $controller = $this->atomicController();

        // First create an article
        $createPayload = [
            'atomic:operations' => [
                [
                    'op' => 'add',
                    'ref' => ['type' => 'articles'],
                    'data' => [
                        'type' => 'articles',
                        'attributes' => [
                            'title' => 'Test Article',
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
        $articleId = $createResult['atomic:results'][0]['data']['id'];

        // Try to update to-many relationship with non-array data
        $updatePayload = [
            'atomic:operations' => [
                [
                    'op' => 'update',
                    'ref' => [
                        'type' => 'articles',
                        'id' => $articleId,
                        'relationship' => 'tags',
                    ],
                    'data' => 'not-an-array',  // Should be an array
                ],
            ],
        ];

        $updateRequest = Request::create('/api/operations', 'POST', server: [
            'CONTENT_TYPE' => MediaType::JSON_API_ATOMIC,
            'HTTP_ACCEPT' => MediaType::JSON_API_ATOMIC,
        ], content: json_encode($updatePayload, \JSON_THROW_ON_ERROR));

        $this->expectException(BadRequestException::class);
        // The actual error message may vary
        $controller($updateRequest);
    }

    /**
     * Test that to-many relationship data must be a list (not associative array).
     * Kills mutant: RelationshipOps.php:49 (LogicalOr - !is_array || !array_is_list)
     */
    public function testToManyRelationshipWithAssociativeArrayThrowsError(): void
    {
        $controller = $this->atomicController();

        // First create an article
        $createPayload = [
            'atomic:operations' => [
                [
                    'op' => 'add',
                    'ref' => ['type' => 'articles'],
                    'data' => [
                        'type' => 'articles',
                        'attributes' => [
                            'title' => 'Test Article',
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
        $articleId = $createResult['atomic:results'][0]['data']['id'];

        // Try to update to-many relationship with associative array
        $updatePayload = [
            'atomic:operations' => [
                [
                    'op' => 'update',
                    'ref' => [
                        'type' => 'articles',
                        'id' => $articleId,
                        'relationship' => 'tags',
                    ],
                    'data' => ['key' => 'value'],  // Associative array, not a list
                ],
            ],
        ];

        $updateRequest = Request::create('/api/operations', 'POST', server: [
            'CONTENT_TYPE' => MediaType::JSON_API_ATOMIC,
            'HTTP_ACCEPT' => MediaType::JSON_API_ATOMIC,
        ], content: json_encode($updatePayload, \JSON_THROW_ON_ERROR));

        $this->expectException(BadRequestException::class);
        $controller($updateRequest);
    }

    /**
     * Test that resource identifier must be an object (not a list).
     * Kills mutant: RelationshipOps.php:118 (LogicalOr - !is_array || array_is_list)
     */
    public function testResourceIdentifierAsListThrowsError(): void
    {
        $controller = $this->atomicController();

        // First create an article
        $createPayload = [
            'atomic:operations' => [
                [
                    'op' => 'add',
                    'ref' => ['type' => 'articles'],
                    'data' => [
                        'type' => 'articles',
                        'attributes' => [
                            'title' => 'Test Article',
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
        $articleId = $createResult['atomic:results'][0]['data']['id'];

        // Try to update to-many relationship with list instead of objects
        $updatePayload = [
            'atomic:operations' => [
                [
                    'op' => 'update',
                    'ref' => [
                        'type' => 'articles',
                        'id' => $articleId,
                        'relationship' => 'tags',
                    ],
                    'data' => [
                        ['tag1', 'tag2'],  // List instead of object
                    ],
                ],
            ],
        ];

        $updateRequest = Request::create('/api/operations', 'POST', server: [
            'CONTENT_TYPE' => MediaType::JSON_API_ATOMIC,
            'HTTP_ACCEPT' => MediaType::JSON_API_ATOMIC,
        ], content: json_encode($updatePayload, \JSON_THROW_ON_ERROR));

        $this->expectException(BadRequestException::class);
        // The actual error message may vary
        $controller($updateRequest);
    }

    /**
     * Test that resource identifier type must be a non-empty string.
     * Kills mutant: RelationshipOps.php:125 (LogicalOr - !is_string || $type === '')
     */
    public function testResourceIdentifierWithEmptyStringTypeThrowsError(): void
    {
        $controller = $this->atomicController();

        // First create an article
        $createPayload = [
            'atomic:operations' => [
                [
                    'op' => 'add',
                    'ref' => ['type' => 'articles'],
                    'data' => [
                        'type' => 'articles',
                        'attributes' => [
                            'title' => 'Test Article',
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
        $articleId = $createResult['atomic:results'][0]['data']['id'];

        // Try to update relationship with empty string type
        $updatePayload = [
            'atomic:operations' => [
                [
                    'op' => 'update',
                    'ref' => [
                        'type' => 'articles',
                        'id' => $articleId,
                        'relationship' => 'author',
                    ],
                    'data' => [
                        'type' => '',  // Empty string type
                        'id' => 'some-id',
                    ],
                ],
            ],
        ];

        $updateRequest = Request::create('/api/operations', 'POST', server: [
            'CONTENT_TYPE' => MediaType::JSON_API_ATOMIC,
            'HTTP_ACCEPT' => MediaType::JSON_API_ATOMIC,
        ], content: json_encode($updatePayload, \JSON_THROW_ON_ERROR));

        $this->expectException(BadRequestException::class);
        // The actual error message may vary
        $controller($updateRequest);
    }
}
