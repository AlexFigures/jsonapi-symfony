<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Integration\Fixtures\Concurrency;

use AlexFigures\JsonApi\Bridge\Doctrine\Concurrency\DoctrineWriteConcurrencyGuard;
use AlexFigures\JsonApi\Bridge\Doctrine\Flush\FlushManager;
use AlexFigures\JsonApi\Bridge\Doctrine\Instantiator\SerializerEntityInstantiator;
use AlexFigures\JsonApi\Bridge\Doctrine\Persister\ValidatingDoctrineProcessor;
use AlexFigures\JsonApi\Bridge\Doctrine\Repository\GenericDoctrineRepository;
use AlexFigures\JsonApi\Bridge\Doctrine\Transaction\DoctrineTransactionManager;
use AlexFigures\JsonApi\Bridge\Symfony\EventSubscriber\CachePreconditionsSubscriber;
use AlexFigures\JsonApi\Filter\Compiler\Doctrine\DoctrineFilterCompiler;
use AlexFigures\JsonApi\Filter\Handler\Registry\FilterHandlerRegistry;
use AlexFigures\JsonApi\Filter\Handler\Registry\SortHandlerRegistry;
use AlexFigures\JsonApi\Filter\Operator\Registry;
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
use AlexFigures\JsonApi\Http\Document\DocumentBuilder;
use AlexFigures\JsonApi\Http\Error\CorrelationIdProvider;
use AlexFigures\JsonApi\Http\Error\ErrorBuilder;
use AlexFigures\JsonApi\Http\Error\ErrorMapper;
use AlexFigures\JsonApi\Http\Error\JsonApiExceptionListener;
use AlexFigures\JsonApi\Http\Link\LinkGenerator;
use AlexFigures\JsonApi\Http\Request\FilteringWhitelist;
use AlexFigures\JsonApi\Http\Request\PaginationConfig;
use AlexFigures\JsonApi\Http\Request\QueryParser;
use AlexFigures\JsonApi\Http\Request\SortingWhitelist;
use AlexFigures\JsonApi\Http\Validation\ConstraintViolationMapper;
use AlexFigures\JsonApi\Http\Validation\DatabaseErrorMapper;
use AlexFigures\JsonApi\Http\Write\ChangeSetFactory;
use AlexFigures\JsonApi\Http\Write\InputDocumentValidator;
use AlexFigures\JsonApi\Http\Write\WriteConfig;
use AlexFigures\JsonApi\Resource\Mapper\DefaultReadMapper;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistry;
use AlexFigures\JsonApi\Resource\Relationship\RelationshipResolver;
use AlexFigures\JsonApi\Tests\Fixtures\Doctrine\TestManagerRegistry;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\GeneratedRecord;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Validator\Validation;

/** Real HttpKernel + bundle controllers, with no example application dependency. */
final readonly class WritePreconditionsHarness
{
    private HttpKernel $kernel;
    private ResourceController $read;
    private UpdateResourceController $update;
    private DeleteResourceController $delete;

    public function __construct(EntityManagerInterface $em, bool $required = false)
    {
        $resources = new ResourceRegistry([GeneratedRecord::class]);
        $managers = new TestManagerRegistry(['default' => $em]);
        $accessor = PropertyAccess::createPropertyAccessor();
        $errors = new ErrorMapper(new ErrorBuilder(false));
        $flush = new FlushManager($managers, new DatabaseErrorMapper($resources, $errors), $resources);
        $transactions = new DoctrineTransactionManager($managers, $flush);
        $handlers = new FilterHandlerRegistry();
        $repository = new GenericDoctrineRepository($managers, $resources, new DoctrineFilterCompiler(new Registry([]), $handlers), $handlers, new SortHandlerRegistry(), new DefaultReadMapper());
        $routes = new RouteCollection();
        $routes->add('jsonapi.generated-records.show', new Route('/api/generated-records/{id}'));
        $routes->add('jsonapi.generated-records.related.parent', new Route('/api/generated-records/{id}/parent'));
        $routes->add('jsonapi.generated-records.relationships.parent.show', new Route('/api/generated-records/{id}/relationships/parent'));
        $links = new LinkGenerator(new UrlGenerator($routes, new RequestContext()));
        $preloader = new \AlexFigures\JsonApi\Bridge\Doctrine\Read\DoctrineRepresentationPreloader($managers, $resources, $accessor, new DefaultReadMapper(), new \AlexFigures\JsonApi\Http\Document\Fetch\RepresentationFetchPlanner('always'), $errors, []);
        $document = new DocumentBuilder($resources, $accessor, $links, 'always', preloader: $preloader);
        $policy = new OperationValidator($errors);
        $responses = new JsonApiResponseFactory();
        $parser = new QueryParser($resources, new PaginationConfig(), new SortingWhitelist($resources), new FilteringWhitelist($resources, $errors), $errors, new FilterParser());
        $this->read = new ResourceController($resources, $policy, $responses, $repository, $parser, $document, $errors);
        $violationMapper = new ConstraintViolationMapper($resources, $errors);
        $processor = new ValidatingDoctrineProcessor($managers, $resources, $accessor, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(), $violationMapper, new SerializerEntityInstantiator($managers, $accessor), new RelationshipResolver($managers, $resources, $accessor), $flush);
        $events = new EventDispatcher();
        $this->update = new UpdateResourceController($resources, $policy, new RequestDecoder($errors), $responses, new InputDocumentValidator($resources, new WriteConfig(true), $errors), new ChangeSetFactory($resources), $processor, $transactions, $document, $violationMapper, $events);
        $this->delete = new DeleteResourceController($resources, $policy, $processor, $transactions, $events);
        $config = ['conditional' => ['require_if_match_on_write' => $required]];
        $events->addSubscriber(new CachePreconditionsSubscriber($config, new CacheKeyBuilder(), new HashEtagGenerator(), new LastModifiedResolver(), new ConditionalRequestEvaluator($errors, $config), new HeadersApplier([]), new SurrogateKeyBuilder(), $this->read, concurrency: new DoctrineWriteConcurrencyGuard($managers, $resources, $transactions)));
        $events->addSubscriber(new JsonApiExceptionListener($errors, new CorrelationIdProvider(), false, false));
        $this->kernel = new HttpKernel($events, new ControllerResolver(), new RequestStack(), new ArgumentResolver());
    }

    public function request(string $method, string $id, ?string $etag = null, string $name = 'Changed'): Response
    {
        $request = Request::create('/api/generated-records/' . $id, $method, server: ['CONTENT_TYPE' => 'application/vnd.api+json', 'HTTP_ACCEPT' => 'application/vnd.api+json'], content: json_encode(['data' => ['type' => 'generated-records', 'id' => $id, 'attributes' => ['name' => $name]]], \JSON_THROW_ON_ERROR));
        $request->attributes->add(['type' => 'generated-records', 'id' => $id, '_controller' => match ($method) {
            'GET', 'HEAD' => $this->read, 'DELETE' => $this->delete, default => $this->update
        }]);
        if ($etag !== null) {
            $request->headers->set('If-Match', $etag);
        }
        return $this->kernel->handle($request);
    }
}
