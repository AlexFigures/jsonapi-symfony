<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\JsonApiStatus;

use AlexFigures\Symfony\Http\Exception\NotFoundException;
use AlexFigures\Symfony\Tests\Functional\JsonApiTestCase;
use Symfony\Component\HttpFoundation\Response;

final class DeleteOperationStatusTest extends JsonApiTestCase
{
    public function testDeleteReturns204(): void
    {
        $response = ($this->deleteController())('articles', '1');

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertSame('', $response->getContent());
    }

    public function testDeleteNonExistingResourceReturns404(): void
    {
        try {
            ($this->deleteController())('articles', 'missing-resource');
            self::fail('Expected NotFoundException (404) for missing resource.');
        } catch (NotFoundException $exception) {
            self::assertSame(404, $exception->getStatusCode());
        }
    }

    public function testDeleteAsyncAcceptedIsNotApplicable(): void
    {
        self::markTestSkipped('Bundle does not support async deletions; 202 Accepted not applicable.');
    }
}
