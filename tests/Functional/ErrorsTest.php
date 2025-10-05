<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Functional;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ErrorsTest extends JsonApiTestCase
{
    public function testUnknownTypeResultsIn404(): void
    {
        $request = Request::create('/api/unknown', 'GET');

        $this->expectException(NotFoundHttpException::class);

        ($this->collectionController())($request, 'unknown');
    }

    public function testUnknownFieldResultsIn400(): void
    {
        $request = Request::create('/api/articles', 'GET', ['fields' => ['articles' => 'unknown']]);

        $this->expectException(BadRequestHttpException::class);

        ($this->collectionController())($request, 'articles');
    }

    public function testInvalidIncludeResultsIn400(): void
    {
        $request = Request::create('/api/articles', 'GET', ['include' => 'unknown']);

        $this->expectException(BadRequestHttpException::class);

        ($this->collectionController())($request, 'articles');
    }
}
