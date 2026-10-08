<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Unit\Http\Cache;

use AlexFigures\JsonApi\Http\Cache\LastModifiedResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class LastModifiedResolverTest extends TestCase
{
    public function testCollectionMaximumCanBeDisabledWithoutDisablingItemsOrExplicitHeader(): void
    {
        $resolver = new LastModifiedResolver(['last_modified' => ['collections_max_of' => false]]);
        $request = new Request();
        $request->attributes->set('_jsonapi_collection', true);
        $request->attributes->set('_jsonapi_models', [(object) ['updatedAt' => new \DateTimeImmutable('2020-01-01')]]);
        self::assertNull($resolver->resolve($request, new Response()));
        $request->attributes->remove('_jsonapi_collection');
        self::assertSame('2020-01-01', $resolver->resolve($request, new Response())->format('Y-m-d'));
        $request->attributes->set('_jsonapi_collection', true);
        $response = new Response();
        $response->setLastModified(new \DateTimeImmutable('2021-01-01'));
        self::assertSame('2021-01-01', $resolver->resolve($request, $response)->format('Y-m-d'));
        self::assertSame('2020-01-01', (new LastModifiedResolver())->resolve($request, new Response())->format('Y-m-d'));
    }
}
