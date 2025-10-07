<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional\Atomic;

use JsonApi\Symfony\Http\Exception\BadRequestException;
use JsonApi\Symfony\Http\Negotiation\MediaType;
use JsonApi\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests edge cases for AddHandler to kill escaped mutants.
 * 
 * Targets escaped mutants in src/Atomic/Execution/Handlers/AddHandler.php:
 * - Type extraction logic (lines 34-37)
 * - Empty string validation (line 56)
 * - Client ID handling
 * - LID association
 */
final class AddHandlerEdgeCasesTest extends JsonApiTestCase
{
    /**
     * Test that type can be extracted from data when using href (ref is null).
     * Kills mutant: AddHandler.php:34 (NullSafePropertyCall - $operation->ref?->type)
     * Kills mutant: AddHandler.php:35 (Identical - $type === null check)
     */
    public function testAddOperationWithHrefUsesDataType(): void
    {
        $controller = $this->atomicController();

        $payload = [
            'atomic:operations' => [
                [
                    'op' => 'add',
                    'href' => '/api/authors',  // Using href instead of ref
                    'data' => [
                        'type' => 'authors',
                        'attributes' => [
                            'name' => 'Author With Href',
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
        $result = json_decode($response->getContent(), true);
        self::assertArrayHasKey('atomic:results', $result);
        self::assertSame('authors', $result['atomic:results'][0]['data']['type']);
    }

    /**
     * Test that empty string in data.type is rejected.
     * Kills mutant: AddHandler.php:35 (NotIdentical - $data['type'] !== '' check)
     */
    public function testAddOperationWithEmptyStringTypeThrowsError(): void
    {
        $controller = $this->atomicController();

        $payload = [
            'atomic:operations' => [
                [
                    'op' => 'add',
                    'data' => [
                        'type' => '',  // Empty string should be rejected
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
     * Kills mutant: AddHandler.php:35 (LogicalAndSingleSubExprNegation - !is_string check)
     */
    public function testAddOperationWithNonStringTypeThrowsError(): void
    {
        $controller = $this->atomicController();

        $payload = [
            'atomic:operations' => [
                [
                    'op' => 'add',
                    'data' => [
                        'type' => 123,  // Non-string type should be rejected
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
     * Test that client-provided ID is used when valid.
     * Kills mutant: AddHandler.php:56 (NotIdentical - $data['id'] !== '' check)
     */
    public function testAddOperationWithClientProvidedId(): void
    {
        $controller = $this->atomicController();

        $clientId = 'client-provided-id-123';
        $payload = [
            'atomic:operations' => [
                [
                    'op' => 'add',
                    'ref' => ['type' => 'authors'],
                    'data' => [
                        'type' => 'authors',
                        'id' => $clientId,
                        'attributes' => [
                            'name' => 'Author With Client ID',
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
        $result = json_decode($response->getContent(), true);
        self::assertSame($clientId, $result['atomic:results'][0]['data']['id']);
    }

    /**
     * Test that empty string ID is ignored (not used as client ID).
     * Kills mutant: AddHandler.php:56 (NotIdentical - $data['id'] !== '' check)
     */
    public function testAddOperationWithEmptyStringIdIgnoresIt(): void
    {
        $controller = $this->atomicController();

        $payload = [
            'atomic:operations' => [
                [
                    'op' => 'add',
                    'ref' => ['type' => 'authors'],
                    'data' => [
                        'type' => 'authors',
                        'id' => '',  // Empty string should be ignored
                        'attributes' => [
                            'name' => 'Author With Empty ID',
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
        $result = json_decode($response->getContent(), true);
        // Should have a generated ID, not empty string
        self::assertNotEmpty($result['atomic:results'][0]['data']['id']);
        self::assertNotSame('', $result['atomic:results'][0]['data']['id']);
    }

    /**
     * Test that non-string ID is ignored.
     * Kills mutant: AddHandler.php:56 (LogicalAndSingleSubExprNegation - !is_string check)
     */
    public function testAddOperationWithNonStringIdIgnoresIt(): void
    {
        $controller = $this->atomicController();

        $payload = [
            'atomic:operations' => [
                [
                    'op' => 'add',
                    'ref' => ['type' => 'authors'],
                    'data' => [
                        'type' => 'authors',
                        'id' => 12345,  // Non-string ID should be ignored
                        'attributes' => [
                            'name' => 'Author With Numeric ID',
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
        $result = json_decode($response->getContent(), true);
        // Should have a generated ID, not the numeric value
        self::assertIsString($result['atomic:results'][0]['data']['id']);
        self::assertNotSame('12345', $result['atomic:results'][0]['data']['id']);
    }

    /**
     * Test LID association with valid lid.
     * Kills mutants related to LID handling (line 72)
     */
    public function testAddOperationWithLidAssociatesCorrectly(): void
    {
        $controller = $this->atomicController();

        $payload = [
            'atomic:operations' => [
                [
                    'op' => 'add',
                    'ref' => ['type' => 'authors'],
                    'data' => [
                        'type' => 'authors',
                        'lid' => 'temp-author-1',
                        'attributes' => [
                            'name' => 'Author With LID',
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
        $result = json_decode($response->getContent(), true);
        self::assertArrayHasKey('data', $result['atomic:results'][0]);
        self::assertArrayHasKey('id', $result['atomic:results'][0]['data']);
    }
}

