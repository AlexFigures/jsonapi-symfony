<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Integration\Atomic;

use AlexFigures\JsonApi\Bridge\Symfony\EventSubscriber\CachePreconditionsSubscriber;
use AlexFigures\JsonApi\Filter\Parser\FilterParser;
use AlexFigures\JsonApi\Http\Cache\CacheKeyBuilder;
use AlexFigures\JsonApi\Http\Cache\ConditionalRequestEvaluator;
use AlexFigures\JsonApi\Http\Cache\HashEtagGenerator;
use AlexFigures\JsonApi\Http\Cache\HeadersApplier;
use AlexFigures\JsonApi\Http\Cache\LastModifiedResolver;
use AlexFigures\JsonApi\Http\Cache\SurrogateKeyBuilder;
use AlexFigures\JsonApi\Http\Controller\DeleteResourceController;
use AlexFigures\JsonApi\Http\Controller\ResourceController;
use AlexFigures\JsonApi\Http\Controller\Support\JsonApiResponseFactory;
use AlexFigures\JsonApi\Http\Controller\Support\OperationValidator;
use AlexFigures\JsonApi\Http\Controller\Support\RequestDecoder;
use AlexFigures\JsonApi\Http\Controller\UpdateResourceController;
use AlexFigures\JsonApi\Http\Exception\JsonApiHttpException;
use AlexFigures\JsonApi\Http\Request\FilteringWhitelist;
use AlexFigures\JsonApi\Http\Request\PaginationConfig;
use AlexFigures\JsonApi\Http\Request\QueryParser;
use AlexFigures\JsonApi\Http\Request\SortingWhitelist;
use AlexFigures\JsonApi\Http\Write\InputDocumentValidator;
use AlexFigures\JsonApi\Http\Write\WriteConfig;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\GeneratedRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/** PostgreSQL CACHE-001/002 and HTTP-001 regressions. */
final class WritePreconditionsRegressionTest extends DoctrineAtomicTestCase
{
    #[DataProvider('rejections')]
    public function testFailedPreconditionPreventsMutation(string $method, ?string $validator, bool $required, int $status): void
    {
        [$model, $read, $update, $delete, $subscriber] = $this->setupControllers($required);
        $request = $this->writeRequest($model, $method, $validator);
        $controller = $method === 'DELETE' ? $delete : $update;
        try {
            $subscriber->onKernelController(new ControllerEvent($this->kernel(), $controller, $request, HttpKernelInterface::MAIN_REQUEST));
            $method === 'DELETE' ? $controller('generated-records', (string) $model->id) : $controller($request, 'generated-records', (string) $model->id);
            self::fail('The precondition must fail before calling the write controller.');
        } catch (JsonApiHttpException $exception) {
            self::assertSame($status, $exception->getStatusCode());
            self::assertSame((string) $status, $exception->getErrors()[0]->status);
            self::assertSame('If-Match', $exception->getErrors()[0]->source->header);
        }
        $this->em->clear();
        $persisted = $this->em->find(GeneratedRecord::class, $model->id);
        self::assertNotNull($persisted);
        self::assertSame('Original', $persisted->name);
    }

    public static function rejections(): iterable
    {
        yield 'stale patch' => ['PATCH', '"stale"', false, 412];
        yield 'stale delete' => ['DELETE', '"stale"', false, 412];
        yield 'missing patch' => ['PATCH', null, true, 428];
        yield 'missing delete' => ['DELETE', null, true, 428];
        yield 'weak validator' => ['PATCH', 'W/"stale"', false, 412];
    }

    public function testMatchingGetValidatorPermitsWriteAndHeadUsesSameValidator(): void
    {
        [$model, $read, $update, , $subscriber] = $this->setupControllers(false);
        $get = Request::create('/api/generated-records/' . $model->id);
        $response = $read($get, 'generated-records', (string) $model->id);
        $subscriber->onKernelResponse(new ResponseEvent($this->kernel(), $get, HttpKernelInterface::MAIN_REQUEST, $response));
        $etag = $response->getEtag();
        self::assertNotNull($etag);
        self::assertSame('Mon, 01 Jan 2024 00:00:00 GMT', $response->headers->get('Last-Modified'));
        $head = Request::create($get->getUri(), 'HEAD');
        $headResponse = $read($head, 'generated-records', (string) $model->id);
        $subscriber->onKernelResponse(new ResponseEvent($this->kernel(), $head, HttpKernelInterface::MAIN_REQUEST, $headResponse));
        self::assertSame($etag, $headResponse->getEtag());
        self::assertSame('', $headResponse->getContent());
        $patch = $this->writeRequest($model, 'PATCH', $etag);
        $subscriber->onKernelController(new ControllerEvent($this->kernel(), $update, $patch, HttpKernelInterface::MAIN_REQUEST));
        self::assertSame(200, $update($patch, 'generated-records', (string) $model->id)->getStatusCode());
        $this->em->clear();
        self::assertSame('Changed', $this->em->find(GeneratedRecord::class, $model->id)->name);
    }


    public function testWritePreconditionDoesNotRequireShowPermission(): void
    {
        [$model, , $update, , $subscriber] = $this->setupControllers(true);
        $this->registry->getByType('generated-records')->allowedOperations = [\AlexFigures\JsonApi\Resource\Definition\ResourceOperation::UPDATE];
        $request = $this->writeRequest($model, 'PATCH', '*');
        $subscriber->onKernelController(new ControllerEvent($this->kernel(), $update, $request, HttpKernelInterface::MAIN_REQUEST));
        self::assertSame(200, $update($request, 'generated-records', (string) $model->id)->getStatusCode());
        $this->em->clear();
        self::assertSame('Changed', $this->em->find(GeneratedRecord::class, $model->id)->name);
    }

    private function setupControllers(bool $required): array
    {
        $model = new GeneratedRecord();
        $model->name = 'Original';
        $model->publishedAt = new \DateTimeImmutable('2024-01-01T00:00:00Z');
        $this->em->persist($model);
        $this->em->flush();
        $policy = new OperationValidator($this->errorMapper);
        $responses = new JsonApiResponseFactory();
        $parser = new QueryParser($this->registry, new PaginationConfig(), new SortingWhitelist($this->registry), new FilteringWhitelist($this->registry, $this->errorMapper), $this->errorMapper, new FilterParser());
        $read = new ResourceController($this->registry, $policy, $responses, $this->repository, $parser, $this->documentBuilder, $this->errorMapper);
        $update = new UpdateResourceController($this->registry, $policy, new RequestDecoder($this->errorMapper), $responses, new InputDocumentValidator($this->registry, new WriteConfig(true), $this->errorMapper), $this->changeSetFactory, $this->validatingProcessor, $this->transactionManager, $this->documentBuilder, $this->violationMapper, new EventDispatcher());
        $delete = new DeleteResourceController($this->registry, $policy, $this->validatingProcessor, $this->transactionManager, new EventDispatcher());
        $config = ['conditional' => ['require_if_match_on_write' => $required]];
        $subscriber = new CachePreconditionsSubscriber($config, new CacheKeyBuilder(), new HashEtagGenerator(), new LastModifiedResolver(['last_modified' => ['resource_field' => 'publishedAt']]), new ConditionalRequestEvaluator($this->errorMapper, $config), new HeadersApplier([]), new SurrogateKeyBuilder(), $read);
        return [$model, $read, $update, $delete, $subscriber];
    }

    private function writeRequest(GeneratedRecord $model, string $method, ?string $etag): Request
    {
        $request = Request::create('/api/generated-records/' . $model->id, $method, server: ['CONTENT_TYPE' => 'application/vnd.api+json'], content: json_encode(['data' => ['type' => 'generated-records', 'id' => (string) $model->id, 'attributes' => ['name' => 'Changed']]], \JSON_THROW_ON_ERROR));
        $request->attributes->set('type', 'generated-records');
        $request->attributes->set('id', (string) $model->id);
        if ($etag !== null) {
            $request->headers->set('If-Match', $etag);
        }
        return $request;
    }

    private function kernel(): HttpKernelInterface
    {
        return new class () implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }
        };
    }
}
