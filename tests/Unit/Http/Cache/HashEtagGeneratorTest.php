<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Http\Cache;

use JsonApi\Symfony\Http\Cache\HashEtagGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class HashEtagGeneratorTest extends TestCase
{
    public function testGenerateReturnsRawHash(): void
    {
        $generator = new HashEtagGenerator(['etag' => ['hash_algo' => 'md5']]);
        $request = new Request();
        $response = new Response('content');

        $etag = $generator->generate($request, $response, 'cache-key', false);

        self::assertSame(hash('md5', 'cache-key\\ncontent'), $etag);
    }
}
