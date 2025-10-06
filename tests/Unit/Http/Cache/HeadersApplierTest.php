<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Http\Cache;

use JsonApi\Symfony\Http\Cache\HeadersApplier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class HeadersApplierTest extends TestCase
{
    public function testAppliesStrongEtagWithoutDoubleQuoting(): void
    {
        $response = new Response();
        $applier = new HeadersApplier([]);

        $applier->apply($response, 'hash', null);

        self::assertSame('"hash"', $response->headers->get('ETag'));
    }

    public function testAppliesWeakEtagFlag(): void
    {
        $response = new Response();
        $applier = new HeadersApplier([]);

        $applier->apply($response, 'hash', null, [], true);

        self::assertSame('W/"hash"', $response->headers->get('ETag'));
    }
}
