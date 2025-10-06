<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Http\Cache;

use JsonApi\Symfony\Http\Cache\ConditionalRequestEvaluator;
use JsonApi\Symfony\Http\Error\ErrorBuilder;
use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Exception\PreconditionFailedException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ConditionalRequestEvaluatorTest extends TestCase
{
    public function testIfNoneMatchTriggersNotModifiedForQuotedValidator(): void
    {
        $evaluator = $this->createEvaluator();
        $request = Request::create('/articles', 'GET');
        $request->headers->set('If-None-Match', '"hash"');
        $response = new Response();

        $evaluator->evaluate($request, $response, 'hash', null);

        self::assertSame(Response::HTTP_NOT_MODIFIED, $response->getStatusCode());
    }

    public function testIfNoneMatchHandlesWeakValidators(): void
    {
        $evaluator = $this->createEvaluator();
        $request = Request::create('/articles', 'GET');
        $request->headers->set('If-None-Match', 'W/"hash"');
        $response = new Response();

        $evaluator->evaluate($request, $response, 'hash', null);

        self::assertSame(Response::HTTP_NOT_MODIFIED, $response->getStatusCode());
    }

    public function testIfMatchRejectsWeakValidators(): void
    {
        $evaluator = $this->createEvaluator();
        $request = Request::create('/articles', 'PATCH');
        $request->headers->set('If-Match', 'W/"hash"');

        $this->expectException(PreconditionFailedException::class);

        $evaluator->evaluate($request, new Response(), 'hash', null, false);
    }

    public function testIfMatchAcceptsStrongValidators(): void
    {
        $evaluator = $this->createEvaluator();
        $request = Request::create('/articles', 'PATCH');
        $request->headers->set('If-Match', '"hash"');

        $evaluator->evaluate($request, new Response(), 'hash', null, false);

        $this->addToAssertionCount(1);
    }

    private function createEvaluator(): ConditionalRequestEvaluator
    {
        $builder = new ErrorBuilder(true);
        $mapper = new ErrorMapper($builder);

        return new ConditionalRequestEvaluator($mapper);
    }
}
