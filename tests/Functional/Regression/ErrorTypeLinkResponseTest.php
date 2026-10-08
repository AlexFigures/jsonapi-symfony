<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Functional\Regression;

use PHPUnit\Framework\TestCase;

final class ErrorTypeLinkResponseTest extends TestCase
{
    public function testFluentFactoryEmitsTypeLinksForSingleAndValidationErrors(): void
    {
        $kernel = new RcKernel('error_links', false);
        $kernel->boot();
        try {
            $factory = $kernel->getContainer()->get('test.service_container')->get('test.error_response_factory');
            $base = $factory->error(403, 'Forbidden.')->withLinks(['about' => '/document'])->withTypeLink('/problems/forbidden');
            $response = $base->build();
            self::assertSame(403, $response->getStatusCode());
            $document = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            self::assertSame(['type' => '/problems/forbidden'], $document['errors'][0]['links']);
            self::assertSame(['about' => '/document'], $document['links']);
            $document = json_decode($base->withTypeLink(null)->build()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            self::assertArrayNotHasKey('links', $document['errors'][0]);
            $response = $factory->validationErrors([
                ['pointer' => '/data/attributes/name', 'detail' => 'Invalid name.'],
                ['pointer' => '/data/attributes/title', 'detail' => 'Invalid title.'],
            ])->withTypeLink('/problems/validation')->build();
            self::assertSame(422, $response->getStatusCode());
            foreach (json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['errors'] as $error) {
                self::assertSame(['type' => '/problems/validation'], $error['links']);
                self::assertSame('422', $error['status']);
            }
        } finally {
            $kernel->shutdown();
        }
    }
}
