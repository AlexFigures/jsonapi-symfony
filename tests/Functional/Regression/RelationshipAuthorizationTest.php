<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional\Regression;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class RelationshipAuthorizationTest extends TestCase
{
    #[DataProvider('operations')]
    public function testDeniedEndpointsDoNotReadWriteOrStartTransaction(string $method, string $suffix, string $permission): void
    {
        $kernel = new RcKernel('authorized_relationships', false);
        try {
            foreach (['', '*', '"stale"'] as $validator) {
                $response = $kernel->handle($this->request($method, $suffix, 'unrelated_permission', $validator));
                self::assertSame(403, $response->getStatusCode());
                if ($method !== 'HEAD') {
                    self::assertSame('403', json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['errors'][0]['status']);
                }
            }
            $services = $kernel->getContainer()->get('test.service_container');
            $handler = $services->get(RcTypedRelationshipHandler::class);
            self::assertSame([], $handler->reads);
            self::assertSame([], $handler->writes);
            self::assertSame(0, $services->get(RcRelationshipTransaction::class)->calls);
            self::assertSame(0, $services->get(RcRelationshipTransaction::class)->protections);
        } finally {
            $kernel->shutdown();
        }
    }

    #[DataProvider('operations')]
    public function testAllowedOperationReachesProvider(string $method, string $suffix, string $permission): void
    {
        $kernel = new RcKernel('authorized_relationships', false);
        try {
            $response = $kernel->handle($this->request($method, $suffix, $permission, '*'), catch: false);
            self::assertSame(200, $response->getStatusCode());
            $services = $kernel->getContainer()->get('test.service_container');
            $handler = $services->get(RcTypedRelationshipHandler::class);
            if (in_array($method, ['PATCH', 'POST', 'DELETE'], true)) {
                self::assertCount(1, $handler->writes);
                self::assertSame(1, $services->get(RcRelationshipTransaction::class)->calls);
                self::assertSame(1, $services->get(RcRelationshipTransaction::class)->protections);
            } else {
                self::assertNotEmpty($handler->reads);
                self::assertSame([], $handler->writes);
            }
        } finally {
            $kernel->shutdown();
        }
    }

    public static function operations(): iterable
    {
        yield 'linkage to-one' => ['GET', 'relationships/one', 'read_linkage'];
        yield 'linkage to-many' => ['GET', 'relationships/many', 'read_linkage'];
        yield 'linkage HEAD' => ['HEAD', 'relationships/many', 'read_linkage'];
        yield 'related to-one' => ['GET', 'one', 'read_related'];
        yield 'related to-many' => ['GET', 'many', 'read_related'];
        yield 'related HEAD' => ['HEAD', 'many', 'read_related'];
        yield 'replace to-one' => ['PATCH', 'relationships/one', 'replace'];
        yield 'replace to-many' => ['PATCH', 'relationships/many', 'replace'];
        yield 'add to-many' => ['POST', 'relationships/many', 'add'];
        yield 'remove to-many' => ['DELETE', 'relationships/many', 'remove'];
    }

    private function request(string $method, string $suffix, string $permission, string $validator): Request
    {
        $target = ['type' => 'rc-memory', 'id' => 'stored'];
        return Request::create('/api/rc-tagged/stored/' . $suffix, $method, server: [
            'CONTENT_TYPE' => 'application/vnd.api+json',
            'HTTP_ACCEPT' => 'application/vnd.api+json',
            'HTTP_IF_MATCH' => $validator,
            'HTTP_X_RELATIONSHIP_PERMISSION' => $permission,
        ], content: json_encode(['data' => str_ends_with($suffix, 'many') ? [$target] : $target], \JSON_THROW_ON_ERROR));
    }
}
