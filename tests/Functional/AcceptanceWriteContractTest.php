<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Functional;

use AlexFigures\Symfony\Http\Exception\JsonApiHttpException;
use AlexFigures\Symfony\Http\Write\InputDocumentValidator;
use AlexFigures\Symfony\Http\Write\WriteConfig;
use AlexFigures\Symfony\Resource\Definition\ResourceOperation;
use AlexFigures\Symfony\Tests\Fixtures\Model\Article;
use AlexFigures\Symfony\Tests\Fixtures\Model\Author;
use AlexFigures\Symfony\Tests\Fixtures\Model\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;

final class AcceptanceWriteContractTest extends JsonApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $author = new Author('1', 'Name');
        $tag = new Tag('1', 'PHP');
        $article = new Article('1', 'Original', new \DateTimeImmutable('2024-01-01'), $author, $tag);
        $this->repository()->save('authors', $author);
        $this->repository()->save('tags', $tag);
        $this->repository()->save('articles', $article);
    }

    public function testEmptyAttributeObjectIsValidButArrayIsRejected(): void
    {
        $request = Request::create('/api/articles/1', 'PATCH', server: ['CONTENT_TYPE' => 'application/vnd.api+json'], content: '{"data":{"type":"articles","id":"1","attributes":{},"relationships":{}}}');
        self::assertSame(200, ($this->updateController())($request, 'articles', '1')->getStatusCode());
        $request = Request::create('/api/articles/1', 'PATCH', server: ['CONTENT_TYPE' => 'application/vnd.api+json'], content: '{"data":{"type":"articles","id":"1","attributes":[]}}');
        try {
            ($this->updateController())($request, 'articles', '1');
            self::fail('An empty array cannot be accepted as an attribute object.');
        } catch (JsonApiHttpException $exception) {
            self::assertSame(400, $exception->getStatusCode());
            self::assertSame('/data/attributes', $exception->getErrors()[0]->source->pointer);
        }
    }

    #[DataProvider('invalidRelationships')]
    public function testResourceLinkageBoundary(array $relationships, int $status, string $pointer): void
    {
        $validator = new InputDocumentValidator($this->registry(), new WriteConfig(true), $this->errorMapper());
        try {
            $validator->validateAndExtract('articles', '1', ['data' => ['type' => 'articles', 'id' => '1', 'relationships' => $relationships]], 'PATCH');
            self::fail('Expected a linkage error.');
        } catch (JsonApiHttpException $exception) {
            self::assertSame($status, $exception->getStatusCode());
            self::assertSame($pointer, $exception->getErrors()[0]->source->pointer);
        }
    }

    public static function invalidRelationships(): iterable
    {
        yield 'unknown relationship' => [['unknown' => ['data' => null]], 400, '/data/relationships/unknown'];
        yield 'to-one list' => [['author' => ['data' => [['type' => 'authors', 'id' => '1']]]], 400, '/data/relationships/author/data'];
        yield 'to-many object' => [['tags' => ['data' => ['type' => 'tags', 'id' => '1']]], 400, '/data/relationships/tags/data'];
        yield 'wrong type' => [['author' => ['data' => ['type' => 'tags', 'id' => '1']]], 409, '/data/relationships/author/data/type'];
        yield 'id and lid' => [['author' => ['data' => ['type' => 'authors', 'id' => '1', 'lid' => 'local']]], 400, '/data/relationships/author/data'];
    }

    #[DataProvider('invalidAtomicTargets')]
    public function testAtomicTargetBoundary(array $operation, int $status): void
    {
        $request = Request::create('/api/operations', 'POST', server: ['CONTENT_TYPE' => 'application/vnd.api+json;ext="https://jsonapi.org/ext/atomic"'], content: json_encode(['atomic:operations' => [$operation]], \JSON_THROW_ON_ERROR));
        try {
            ($this->atomicController())($request);
            self::fail('Expected invalid target rejection.');
        } catch (JsonApiHttpException $exception) {
            self::assertSame($status, $exception->getStatusCode());
        }
        self::assertNotNull($this->repository()->findOne('articles', '1', new \AlexFigures\Symfony\Query\Criteria()));
    }

    public static function invalidAtomicTargets(): iterable
    {
        yield 'trailing garbage' => [['op' => 'remove', 'href' => '/api/articles/1/unrecognized/segments'], 400];
        yield 'incomplete relationship' => [['op' => 'remove', 'href' => '/api/articles/1/relationships'], 400];
        yield 'external origin' => [['op' => 'remove', 'href' => 'https://other.test/api/articles/1'], 400];
        yield 'malformed linkage entry' => [['op' => 'update', 'ref' => ['type' => 'articles', 'id' => '1'], 'data' => ['type' => 'articles', 'relationships' => ['tags' => ['data' => ['bad']]]]], 400];
        yield 'ref both identifiers' => [['op' => 'remove', 'ref' => ['type' => 'articles', 'id' => '1', 'lid' => 'local']], 400];
    }

    #[DataProvider('validHrefs')]
    public function testAtomicUriReferencesResolveSameTarget(string $href): void
    {
        $request = Request::create('http://localhost/api/operations', 'POST', server: ['CONTENT_TYPE' => 'application/vnd.api+json;ext="https://jsonapi.org/ext/atomic"'], content: json_encode(['atomic:operations' => [['op' => 'update', 'href' => $href, 'data' => ['type' => 'articles', 'id' => '1', 'attributes' => ['title' => 'Changed']]]]], \JSON_THROW_ON_ERROR));
        self::assertSame(200, ($this->atomicController())($request)->getStatusCode());
    }

    public static function validHrefs(): iterable
    {
        yield ['/api/articles/1'];
        yield ['articles/1'];
        yield ['http://localhost/api/articles/1'];
    }

    public function testAtomicReadonlyPolicyAndPublicErrorPointers(): void
    {
        $metadata = $this->registry()->getByType('articles');
        $metadata->allowedOperations = [ResourceOperation::SHOW];
        $request = Request::create('/api/operations', 'POST', server: ['CONTENT_TYPE' => 'application/vnd.api+json;ext="https://jsonapi.org/ext/atomic"'], content: '{"atomic:operations":[{"op":"update","ref":{"type":"articles","id":"1"},"data":{"type":"articles","attributes":{"title":"Changed"}}}]}');
        try {
            ($this->atomicController())($request);
            self::fail('Read-only policy must reject Atomic writes.');
        } catch (JsonApiHttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
        }
        $metadata->allowedOperations = [ResourceOperation::SHOW, ResourceOperation::UPDATE];
        $request = Request::create('/api/operations', 'POST', server: ['CONTENT_TYPE' => 'application/vnd.api+json;ext="https://jsonapi.org/ext/atomic"'], content: '{"atomic:operations":[{"op":"update","ref":{"type":"articles","id":"1"},"data":{"type":"articles","attributes":{"unknown":"Changed"}}}]}');
        try {
            ($this->atomicController())($request);
            self::fail('Expected unknown attribute error.');
        } catch (JsonApiHttpException $exception) {
            self::assertSame('/atomic:operations/0/data/attributes/unknown', $exception->getErrors()[0]->source->pointer);
        }
    }

    public function testOptionsToOneCardinalityAndRequestedEmptyInclude(): void
    {
        $allow = $this->optionsController()->relationship('articles', 'author')->headers->get('Allow');
        self::assertStringContainsString('PATCH', $allow);
        self::assertStringNotContainsString('POST', $allow);
        self::assertStringNotContainsString('DELETE', $allow);
        $author = new Author('1', 'Name');
        $this->repository()->save('articles', new Article('1', 'Original', new \DateTimeImmutable('2024-01-01'), $author));
        $request = Request::create('/api/articles/1?include=tags');
        $document = json_decode((string) ($this->resourceController())($request, 'articles', '1')->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('included', $document);
        self::assertSame([], $document['included']);
    }

    public function testHeadRetainsGetRepresentationForAllReadRouteKinds(): void
    {
        $cases = [
            [$this->collectionController(), '/api/articles', ['articles']],
            [$this->resourceController(), '/api/articles/1', ['articles', '1']],
            [$this->relationshipGetController(), '/api/articles/1/relationships/author', ['articles', '1', 'author']],
            [$this->relatedController(), '/api/articles/1/author', ['articles', '1', 'author']],
        ];
        $generator = new \AlexFigures\Symfony\Http\Cache\HashEtagGenerator();
        $keys = new \AlexFigures\Symfony\Http\Cache\CacheKeyBuilder();
        foreach ($cases as [$controller, $path, $arguments]) {
            $get = Request::create($path);
            $head = Request::create($path, 'HEAD');
            $getResponse = $controller($get, ...$arguments);
            $headResponse = $controller($head, ...$arguments);
            self::assertSame('', $headResponse->getContent());
            self::assertSame($generator->generate($get, $getResponse, $keys->build($get), false), $generator->generate($head, $headResponse, $keys->build($head), false), $path);
        }
    }


    public function testStandaloneLinkageIncludesRelatedNavigation(): void
    {
        $request = Request::create('/api/articles/1/relationships/author');
        $response = ($this->relationshipGetController())($request, 'articles', '1', 'author');
        $document = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('http://localhost/api/articles/1/relationships/author', $document['links']['self']);
        self::assertStringEndsWith('/api/articles/1/author', $document['links']['related']);
    }

}
