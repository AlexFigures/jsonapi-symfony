<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Http\Cache;

use AlexFigures\Symfony\Http\Cache\VersionEtagGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class VersionEtagGeneratorTest extends TestCase
{
    public function testGenerateReturnsNormalizedHeaderValue(): void
    {
        $generator = new VersionEtagGenerator('X-Version');
        $request = new Request();
        $response = new Response();
        $response->headers->set('X-Version', '  v1  ');

        $etag = $generator->generate($request, $response, 'cache-key', false);

        self::assertSame('v1', $etag);
    }
}
