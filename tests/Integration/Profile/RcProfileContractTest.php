<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Profile;

use AlexFigures\Symfony\Bridge\Doctrine\Instantiator\SerializerEntityInstantiator;
use AlexFigures\Symfony\Bridge\Doctrine\Persister\DoctrineWriteRequestMapper;
use AlexFigures\Symfony\Bridge\Doctrine\Persister\ValidatingDoctrineProcessor;
use AlexFigures\Symfony\Bridge\Doctrine\Profile\ProfileWriteHooks;
use AlexFigures\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler;
use AlexFigures\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository;
use AlexFigures\Symfony\Contract\Data\ChangeSet;
use AlexFigures\Symfony\Contract\Data\ResourceIdentifier;
use AlexFigures\Symfony\Filter\Compiler\Doctrine\DoctrineFilterCompiler;
use AlexFigures\Symfony\Filter\Handler\Registry\FilterHandlerRegistry;
use AlexFigures\Symfony\Filter\Handler\Registry\SortHandlerRegistry;
use AlexFigures\Symfony\Filter\Operator\Registry;
use AlexFigures\Symfony\Http\Exception\ForbiddenException;
use AlexFigures\Symfony\Http\Exception\ValidationException;
use AlexFigures\Symfony\Http\Request\FilteringWhitelist;
use AlexFigures\Symfony\Http\Request\PaginationConfig;
use AlexFigures\Symfony\Http\Request\QueryParser;
use AlexFigures\Symfony\Http\Request\SortingWhitelist;
use AlexFigures\Symfony\Profile\Builtin\SoftDeleteProfile;
use AlexFigures\Symfony\Profile\Hook\ReadHook;
use AlexFigures\Symfony\Profile\Hook\RelationshipHook;
use AlexFigures\Symfony\Profile\ProfileContext;
use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Resource\Definition\ReadProjection;
use AlexFigures\Symfony\Resource\Definition\VersionDefinition;
use AlexFigures\Symfony\Resource\Definition\VersionResolverInterface;
use AlexFigures\Symfony\Resource\Mapper\DefaultReadMapper;
use AlexFigures\Symfony\Resource\Mapper\DefaultWriteMapper;
use AlexFigures\Symfony\Resource\Relationship\RelationshipResolver;
use AlexFigures\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Dto\ArticleViewDto;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\SoftDeletableArticle;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Tag;
use AlexFigures\Symfony\Tests\Util\FakeProfile;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints as Assert;

final class RcProfileContractTest extends DoctrineIntegrationTestCase
{
    protected function getDatabaseUrl(): string
    {
        return $_ENV['DATABASE_URL_POSTGRES'] ?? 'postgresql://jsonapi:secret@postgres:5432/jsonapi_test';
    }
    protected function getPlatform(): string
    {
        return 'postgresql';
    }

    private function stack(object $profile): RequestStack
    {
        $request = Request::create('/api/articles');
        ProfileContext::store($request, new ProfileContext([$profile->uri() => $profile]));
        $stack = new RequestStack();
        $stack->push($request);
        return $stack;
    }

    private function contextualRepository(RequestStack $stack): GenericDoctrineRepository
    {
        $filters = new FilterHandlerRegistry([]);
        return new GenericDoctrineRepository($this->managerRegistry, $this->registry, new DoctrineFilterCompiler(new Registry([]), $filters), $filters, new SortHandlerRegistry(), new DefaultReadMapper(), requests: $stack);
    }

    private function article(): Article
    {
        $article = (new Article())->setId('root')->setTitle('Original title')->setContent('Original content');
        $tag = (new Tag())->setId('tag')->setName('Tag');
        $article->addTag($tag);
        $this->em->persist($tag);
        $this->em->persist($article);
        $this->em->flush();
        return $article;
    }

    public function testReadHooksConstrainCollectionAndItemBeforeSql(): void
    {
        $this->article();
        $hook = new class () implements ReadHook {
            public int $calls = 0;
            private function restrict(Criteria $criteria): void
            {
                ++$this->calls;
                $criteria->customConditions[] = static fn (QueryBuilder $qb) => $qb->andWhere('1 = 0');
            }
            public function onBeforeFindCollection(ProfileContext $context, string $type, Criteria $criteria): void
            {
                $this->restrict($criteria);
            }
            public function onBeforeFindOne(ProfileContext $context, string $type, string $id, Criteria $criteria): void
            {
                $this->restrict($criteria);
            }
        };
        $repository = $this->contextualRepository($this->stack(new FakeProfile('urn:scope', [$hook])));
        $criteria = new Criteria();
        self::assertSame([], $repository->findCollection('articles', $criteria)->items);
        self::assertNull($repository->findOne('articles', 'root', $criteria));
        self::assertSame(2, $hook->calls);
        self::assertSame([], $criteria->customConditions);
    }

    public function testVersionResolverReceivesCurrentRequestContextForBothReads(): void
    {
        $this->article();
        $this->registry->getByType('articles')->versionResolver = new class () implements VersionResolverInterface {
            public function resolve(ProfileContext $context): VersionDefinition
            {
                return $context->has('urn:alternate') ? new VersionDefinition(ArticleViewDto::class, [], ReadProjection::DTO, ['id' => 'e.id', 'title' => 'e.content', 'content' => 'e.title'], []) : new VersionDefinition(null, [], ReadProjection::ENTITY, [], []);
            }
        };
        $stack = $this->stack(new FakeProfile('urn:alternate'));
        $repository = $this->contextualRepository($stack);
        $view = $repository->findOne('articles', 'root', new Criteria());
        self::assertInstanceOf(ArticleViewDto::class, $view);
        self::assertSame('Original content', $view->title);
        $routes = (new \AlexFigures\Symfony\Bridge\Symfony\Routing\JsonApiRouteLoader($this->registry))->load('.', 'jsonapi');
        $links = new \AlexFigures\Symfony\Http\Link\LinkGenerator(new \Symfony\Component\Routing\Generator\UrlGenerator($routes, new \Symfony\Component\Routing\RequestContext()));
        $builder = new \AlexFigures\Symfony\Http\Document\DocumentBuilder($this->registry, $this->accessor, $links);
        // An ordinary GET must honor the DTO shape without requiring sparse fields.
        $representation = new Criteria();
        $document = $builder->buildResource('articles', $view, $representation, $stack->getCurrentRequest());
        self::assertSame('Original content', $document['data']['attributes']['title']);
        self::assertArrayNotHasKey('status', $document['data']['attributes']);
        $slice = $repository->findCollection('articles', new Criteria());
        $collection = $builder->buildCollection('articles', $slice->items, new Criteria(), $slice, $stack->getCurrentRequest());
        self::assertSame('Original content', $collection['data'][0]['attributes']['title']);
        $representation->fields = ['articles' => ['title']];
        self::assertSame(['title' => 'Original content'], $builder->buildResource('articles', $view, $representation, $stack->getCurrentRequest())['data']['attributes']);
        $stack->pop();
        self::assertInstanceOf(Article::class, $repository->findOne('articles', 'root', new Criteria()));
    }

    public function testVersionedDtoWithComputedLinkageAcrossHttpReadControllers(): void
    {
        $article = $this->article();
        $author = (new \AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author())->setId('owner')->setName('Owner')->setEmail('owner@example.test');
        $article->setAuthor($author);
        $this->em->persist($author);
        $this->em->flush();
        $metadata = $this->registry->getByType('articles');
        $metadata->relationships['computedTags'] = new \AlexFigures\Symfony\Resource\Metadata\RelationshipMetadata('computedTags', true, 'tags');
        $this->registry->getByType('authors')->relationships['firstArticle'] = new \AlexFigures\Symfony\Resource\Metadata\RelationshipMetadata('firstArticle', false, 'articles');
        $metadata->versionResolver = new class () implements VersionResolverInterface {
            public function resolve(ProfileContext $context): VersionDefinition
            {
                return $context->has('urn:alternate') ? new VersionDefinition(ArticleViewDto::class, [], ReadProjection::DTO, ['id' => 'e.id', 'title' => 'e.content', 'content' => 'e.title'], []) : new VersionDefinition(null, [], ReadProjection::ENTITY, [], []);
            }
        };
        $stack = $this->stack(new FakeProfile('urn:alternate'));
        $repository = $this->contextualRepository($stack);
        $errors = new \AlexFigures\Symfony\Http\Error\ErrorMapper(new \AlexFigures\Symfony\Http\Error\ErrorBuilder(false));
        $parser = new QueryParser($this->registry, new PaginationConfig(), new SortingWhitelist($this->registry), new FilteringWhitelist($this->registry, $errors), $errors, new \AlexFigures\Symfony\Filter\Parser\FilterParser());
        $routes = (new \AlexFigures\Symfony\Bridge\Symfony\Routing\JsonApiRouteLoader($this->registry))->load('.', 'jsonapi');
        $links = new \AlexFigures\Symfony\Http\Link\LinkGenerator(new \Symfony\Component\Routing\Generator\UrlGenerator($routes, new \Symfony\Component\Routing\RequestContext()));
        $preloader = new \AlexFigures\Symfony\Bridge\Doctrine\Read\DoctrineRepresentationPreloader($this->managerRegistry, $this->registry, $this->accessor, new DefaultReadMapper(), new \AlexFigures\Symfony\Http\Document\Fetch\RepresentationFetchPlanner('always'), $errors, [], $repository, $parser);
        $builder = new \AlexFigures\Symfony\Http\Document\DocumentBuilder($this->registry, $this->accessor, $links, 'always', preloader: $preloader);
        $factory = new \AlexFigures\Symfony\Http\Controller\Support\JsonApiResponseFactory();
        $policy = new \AlexFigures\Symfony\Http\Controller\Support\OperationValidator($errors);
        $item = new \AlexFigures\Symfony\Http\Controller\ResourceController($this->registry, $policy, $factory, $repository, $parser, $builder, $errors);
        $collection = new \AlexFigures\Symfony\Http\Controller\CollectionController($this->registry, $policy, $factory, $repository, $parser, $builder);
        $reader = new GenericDoctrineRelationshipHandler($this->managerRegistry, $this->registry, $this->accessor, $this->flushManager, requests: $stack, repository: $repository);
        $related = new \AlexFigures\Symfony\Http\Controller\RelatedController($this->registry, $reader, $parser, $builder, $errors, $repository);
        foreach (['show', 'index', 'related', 'related-to-one'] as $channel) {
            $this->em->clear();
            $request = $stack->getCurrentRequest();
            $response = match ($channel) {
                'show' => $item($request, 'articles', 'root'),
                'index' => $collection($request, 'articles'),
                'related-to-one' => $related($request, 'authors', 'owner', 'firstArticle'),
                default => $related($request, 'authors', 'owner', 'articles'),
            };
            self::assertSame(200, $response->getStatusCode());
            $doc = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            $resource = in_array($channel, ['show', 'related-to-one'], true) ? $doc['data'] : $doc['data'][0];
            self::assertSame('Original content', $resource['attributes']['title']);
            self::assertSame('root', $resource['id']);
            self::assertArrayNotHasKey('status', $resource['attributes']);
            self::assertSame([['type' => 'tags', 'id' => 'tag']], $resource['relationships']['computedTags']['data']);
            self::assertSame([['type' => 'tags', 'id' => 'tag']], $resource['relationships']['tags']['data']);
        }
        $view = $repository->findOne('articles', 'root', new Criteria());
        $reads = $preloader->preload('articles', [$view], new Criteria(), $stack->getCurrentRequest());
        self::assertSame($view, $reads->model('articles', 'root'), 'Persistence fallback must never replace the DTO representation.');
        self::assertInstanceOf(Article::class, $reads->source('articles', 'root'));
        $strict = new \AlexFigures\Symfony\Bridge\Doctrine\Read\DoctrineRepresentationPreloader($this->managerRegistry, $this->registry, $this->accessor, new DefaultReadMapper(), new \AlexFigures\Symfony\Http\Document\Fetch\RepresentationFetchPlanner('always'), $errors, [], $repository, $parser, unplannedReadPolicy: 'reject');
        try {
            $strict->preload('articles', [$view], new Criteria(), $stack->getCurrentRequest());
            self::fail('Strict policy must continue rejecting unplanned computed getters.');
        } catch (\AlexFigures\Symfony\Http\Exception\BadRequestException $exception) {
            self::assertStringContainsString('computedTags', $exception->getMessage());
        }
        $stack->pop();
        self::assertInstanceOf(Article::class, $repository->findOne('articles', 'root', new Criteria()));
    }

    public function testRenamedAuditAttributeFieldsPersistOnCreateAndUpdate(): void
    {
        $actor = 'creator@example.test';
        $profile = new \AlexFigures\Symfony\Profile\Builtin\AuditTrailProfile(['userProvider' => static function () use (&$actor): string {
            return $actor;
        }], $this->registry);
        $stack = $this->stack($profile);
        $processor = new ValidatingDoctrineProcessor($this->managerRegistry, $this->registry, $this->accessor, $this->validator, $this->violationMapper, new SerializerEntityInstantiator($this->managerRegistry, $this->accessor), new RelationshipResolver($this->managerRegistry, $this->registry, $this->accessor), $this->flushManager, new ProfileWriteHooks($stack, $this->accessor));
        $model = $processor->processCreate('renamed-audit', new ChangeSet(['title' => 'Created']));
        $this->flushManager->flush();
        $this->em->clear();
        $model = $this->em->find(\AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\RenamedAuditRecord::class, 'audit');
        self::assertSame('creator@example.test', $model->insertedBy);
        self::assertInstanceOf(\DateTimeImmutable::class, $model->insertedAt);
        $createdAt = $model->insertedAt;
        $actor = 'editor@example.test';
        $processor->processUpdate('renamed-audit', 'audit', new ChangeSet(['title' => 'Updated']));
        $this->flushManager->flush();
        $this->em->clear();
        $model = $this->em->find(\AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\RenamedAuditRecord::class, 'audit');
        self::assertSame('creator@example.test', $model->insertedBy);
        self::assertEquals($createdAt, $model->insertedAt);
        self::assertSame('editor@example.test', $model->changedBy);
        self::assertInstanceOf(\DateTimeImmutable::class, $model->changedAt);
        $meta = [];
        $context = ProfileContext::fromRequest($stack->getCurrentRequest());
        foreach ($context->documentHooks() as $hook) {
            if ($hook instanceof \AlexFigures\Symfony\Profile\Hook\ResourceMetaHookInterface) {
                $hook->onResourceMeta($context, $this->registry->getByType('renamed-audit'), $meta, $model);
            }
        }
        self::assertSame('creator@example.test', $meta['audit']['createdBy']);
        self::assertSame('editor@example.test', $meta['audit']['updatedBy']);
    }

    public function testRelatedCountPolicySuppressesCountFetchAndDocumentMeta(): void
    {
        $article = $this->article();
        $errors = new \AlexFigures\Symfony\Http\Error\ErrorMapper(new \AlexFigures\Symfony\Http\Error\ErrorBuilder(false));
        foreach ([true, false] as $enabled) {
            $profile = new \AlexFigures\Symfony\Profile\Builtin\RelationshipCountsProfile(['relationship_meta_key' => 'cardinality', 'compute_in_related_endpoints' => $enabled]);
            $stack = $this->stack($profile);
            $request = $stack->getCurrentRequest();
            $request->attributes->set('_jsonapi_related_endpoint', true);
            $repository = $this->contextualRepository($stack);
            $preloader = new \AlexFigures\Symfony\Bridge\Doctrine\Read\DoctrineRepresentationPreloader($this->managerRegistry, $this->registry, $this->accessor, new DefaultReadMapper(), new \AlexFigures\Symfony\Http\Document\Fetch\RepresentationFetchPlanner('never'), $errors, [], $repository);
            $reads = $preloader->preload('articles', [$article], new Criteria(), $request);
            self::assertSame($enabled ? 1 : null, $reads->count('articles', 'root', 'tags'));
            $routes = (new \AlexFigures\Symfony\Bridge\Symfony\Routing\JsonApiRouteLoader($this->registry))->load('.', 'jsonapi');
            $links = new \AlexFigures\Symfony\Http\Link\LinkGenerator(new \Symfony\Component\Routing\Generator\UrlGenerator($routes, new \Symfony\Component\Routing\RequestContext()));
            $builder = new \AlexFigures\Symfony\Http\Document\DocumentBuilder($this->registry, $this->accessor, $links, 'never', preloader: $preloader);
            $document = $builder->buildResource('articles', $article, new Criteria(), $request);
            self::assertSame($enabled ? ['cardinality' => 1] : [], $document['data']['relationships']['tags']['meta'] ?? []);
        }
    }

    public function testPerTypeAuditHooksWithoutNegotiationPersistAndExposeResourceMeta(): void
    {
        $profile = new \AlexFigures\Symfony\Profile\Builtin\AuditTrailProfile(['userProvider' => static fn (): string => 'editor@example.test']);
        $profile->configure(['created_by' => 'createdBy', 'updated_by' => 'updatedBy', 'expose_in_meta' => true]);
        $negotiator = new \AlexFigures\Symfony\Profile\Negotiation\ProfileNegotiator(new \AlexFigures\Symfony\Profile\ProfileRegistry([$profile]), perType: ['auditable-products' => [$profile->uri()]]);
        $request = Request::create('/api/auditable-products', 'POST');
        $request->attributes->set('type', 'auditable-products');
        ProfileContext::store($request, $negotiator->negotiate($request));
        self::assertFalse(ProfileContext::fromRequest($request)->has($profile->uri()), 'A per-type profile must not become global.');
        $stack = new RequestStack();
        $stack->push($request);
        $processor = new ValidatingDoctrineProcessor($this->managerRegistry, $this->registry, $this->accessor, $this->validator, $this->violationMapper, new SerializerEntityInstantiator($this->managerRegistry, $this->accessor), new RelationshipResolver($this->managerRegistry, $this->registry, $this->accessor), $this->flushManager, new ProfileWriteHooks($stack, $this->accessor));
        $product = $processor->processCreate('auditable-products', new ChangeSet(['name' => 'Audited', 'price' => '1.00']));
        $id = $product->getId();
        $this->flushManager->flush();
        $this->em->clear();
        $product = $this->em->find(\AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\AuditableProduct::class, $id);
        self::assertSame('editor@example.test', $product->getCreatedBy());
        $routes = (new \AlexFigures\Symfony\Bridge\Symfony\Routing\JsonApiRouteLoader($this->registry))->load('.', 'jsonapi');
        $links = new \AlexFigures\Symfony\Http\Link\LinkGenerator(new \Symfony\Component\Routing\Generator\UrlGenerator($routes, new \Symfony\Component\Routing\RequestContext()));
        $builder = new \AlexFigures\Symfony\Http\Document\DocumentBuilder($this->registry, $this->accessor, $links);
        $document = $builder->buildResource('auditable-products', $product, new Criteria(), $request);
        self::assertSame('editor@example.test', $document['data']['meta']['audit']['createdBy']);
        self::assertSame($product->getCreatedAt()->format(\DateTimeInterface::ATOM), $document['data']['meta']['audit']['createdAt']);
        $profile->configure(['expose_in_meta' => false]);
        // Explicit service config takes precedence; a separate disabled profile verifies suppression.
        $disabled = new \AlexFigures\Symfony\Profile\Builtin\AuditTrailProfile(['expose_in_meta' => false]);
        ProfileContext::store($request, new ProfileContext([], ['auditable-products' => [$disabled]]));
        self::assertArrayNotHasKey('meta', $builder->buildResource('auditable-products', $product, new Criteria(), $request)['data']);
    }

    public function testCustomSearchRetainsBooleanAstCompositionAndIndependentParameters(): void
    {
        foreach (['alpha', 'beta', 'gamma'] as $title) {
            $article = (new Article())->setId($title)->setTitle($title)->setContent('Body');
            $this->em->persist($article);
        }
        $this->em->flush();
        $handler = new class () implements \AlexFigures\Symfony\Filter\Handler\FilterHandlerInterface {
            public function supports(string $field, string $operator): bool
            {
                return $field === 'search';
            }
            public function getPriority(): int
            {
                return 0;
            }
            public function handle(string $field, string $operator, array $values, object $queryBuilder): void
            {
                if ($operator === 'joined') {
                    $queryBuilder->innerJoin('e.author', 'searched_author')->andWhere('searched_author.name LIKE :search')->setParameter('search', $values[0] . '%');
                } else {
                    $queryBuilder->andWhere('e.title LIKE :search')->setParameter('search', $values[0] . '%');
                }
            }
        };
        $handlers = new FilterHandlerRegistry([$handler]);
        $repository = new GenericDoctrineRepository($this->managerRegistry, $this->registry, new DoctrineFilterCompiler(new Registry([new \AlexFigures\Symfony\Filter\Operator\EqualOperator()]), $handlers), $handlers, new SortHandlerRegistry(), new DefaultReadMapper());
        $criteria = new Criteria();
        $criteria->filter = new \AlexFigures\Symfony\Filter\Ast\Group(new \AlexFigures\Symfony\Filter\Ast\Disjunction([new \AlexFigures\Symfony\Filter\Ast\Comparison('search', 'eq', ['alpha']), new \AlexFigures\Symfony\Filter\Ast\Comparison('id', 'eq', ['beta'])]));
        self::assertSame(['alpha', 'beta'], array_map(static fn (Article $article): string => $article->getId(), $repository->findCollection('articles', $criteria)->items));
        $criteria->filter = new \AlexFigures\Symfony\Filter\Ast\Conjunction([new \AlexFigures\Symfony\Filter\Ast\Disjunction([new \AlexFigures\Symfony\Filter\Ast\Comparison('search', 'eq', ['alpha']), new \AlexFigures\Symfony\Filter\Ast\Comparison('search', 'eq', ['beta'])]), new \AlexFigures\Symfony\Filter\Ast\Comparison('id', 'eq', ['beta'])]);
        self::assertSame(['beta'], array_map(static fn (Article $article): string => $article->getId(), $repository->findCollection('articles', $criteria)->items));
        self::assertNotNull($repository->findOne('articles', 'beta', $criteria));
        self::assertNull($repository->findOne('articles', 'gamma', $criteria));
        $author = (new \AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author())->setId('author')->setName('Alice')->setEmail('alice@example.test');
        $this->em->persist($author);
        $this->em->find(Article::class, 'alpha')->setAuthor($author);
        $this->em->flush();
        $criteria->filter = new \AlexFigures\Symfony\Filter\Ast\Disjunction([new \AlexFigures\Symfony\Filter\Ast\Comparison('search', 'joined', ['Alice']), new \AlexFigures\Symfony\Filter\Ast\Comparison('id', 'eq', ['beta'])]);
        self::assertSame(['alpha', 'beta'], array_map(static fn (Article $article): string => $article->getId(), $repository->findCollection('articles', $criteria)->items), 'The inner join in the search branch must not discard beta, which has no author.');
    }

    #[DataProvider('relationshipMutations')]
    public function testRelationshipHookRejectsBeforeAnyMutation(string $method): void
    {
        $article = $this->article();
        $hook = new class () implements RelationshipHook {
            public function onBeforeRelReplaceToMany(ProfileContext $context, string $type, string $id, string $relationship, array $targets): void
            {
                throw new ForbiddenException('Denied by relationship profile.');
            }
            public function onBeforeRelReplaceToOne(ProfileContext $context, string $type, string $id, string $relationship, ?ResourceIdentifier $target): void
            {
                throw new ForbiddenException('Denied by relationship profile.');
            }
            public function onBeforeRelAddToMany(ProfileContext $context, string $type, string $id, string $relationship, array $targets): void
            {
                throw new ForbiddenException('Denied by relationship profile.');
            }
            public function onBeforeRelRemoveFromToMany(ProfileContext $context, string $type, string $id, string $relationship, array $targets): void
            {
                throw new ForbiddenException('Denied by relationship profile.');
            }
        };
        $stack = $this->stack(new FakeProfile('urn:deny', [$hook]));
        $handler = new GenericDoctrineRelationshipHandler($this->managerRegistry, $this->registry, $this->accessor, $this->flushManager, requests: $stack);
        try {
            if ($method === 'resource') {
                $resolver = new RelationshipResolver($this->managerRegistry, $this->registry, $this->accessor, requests: $stack);
                $resolver->applyRelationships($article, ['tags' => ['data' => []]], $this->registry->getByType('articles'), false);
            } elseif ($method === 'replaceToOne') {
                $handler->replaceToOne('articles', 'root', 'author', null);
            } else {
                $handler->{$method}('articles', 'root', 'tags', [new ResourceIdentifier('tags', 'tag')]);
            }
            self::fail('The relationship hook must reject the mutation.');
        } catch (ForbiddenException) {
            self::assertCount(1, $article->getTags());
            $this->em->clear();
            self::assertCount(1, $this->em->find(Article::class, 'root')->getTags());
        }
    }

    public static function relationshipMutations(): iterable
    {
        foreach (['replaceToMany', 'addToMany', 'removeFromToMany', 'replaceToOne', 'resource'] as $method) {
            yield [$method];
        }
    }

    private function dtoProcessor(RequestStack $stack): ValidatingDoctrineProcessor
    {
        $instantiator = new SerializerEntityInstantiator($this->managerRegistry, $this->accessor);
        $mapper = new DoctrineWriteRequestMapper($instantiator, $this->validator, $this->violationMapper, new DefaultWriteMapper($this->accessor), $stack);
        return new ValidatingDoctrineProcessor($this->managerRegistry, $this->registry, $this->accessor, $this->validator, $this->violationMapper, $instantiator, new RelationshipResolver($this->managerRegistry, $this->registry, $this->accessor), $this->flushManager, writeRequests: $mapper);
    }

    public function testInvalidUpdateDtoNeverChangesManagedEntity(): void
    {
        $article = $this->article();
        $this->registry->getByType('articles')->writeRequests = ['update' => RcInput::class];
        try {
            $this->dtoProcessor(new RequestStack())->processUpdate('articles', 'root', new ChangeSet(['title' => 'Short']));
            self::fail('Input DTO constraints must be evaluated.');
        } catch (ValidationException $error) {
            self::assertSame('422', $error->getErrors()[0]->status);
            self::assertSame('/data/attributes/title', $error->getErrors()[0]->source?->pointer);
            self::assertSame('Original title', $article->getTitle());
        }
    }

    public function testInvalidCreateDtoDoesNotPersist(): void
    {
        $this->registry->getByType('articles')->writeRequests = ['create' => RcInput::class];
        try {
            $this->dtoProcessor(new RequestStack())->processCreate('articles', new ChangeSet(['title' => 'Short']));
            self::fail('Create input must be validated before persistence.');
        } catch (ValidationException $error) {
            self::assertSame('422', $error->getErrors()[0]->status);
            self::assertSame('/data/attributes/title', $error->getErrors()[0]->source?->pointer);
            self::assertSame(0, $this->em->getRepository(Article::class)->count([]));
        }
    }

    public function testValidCreateDtoUsesMapperAndPersists(): void
    {
        $this->registry->getByType('articles')->writeRequests = ['create' => RcInput::class];
        $article = $this->dtoProcessor(new RequestStack())->processCreate('articles', new ChangeSet(['title' => 'A sufficiently long input title', 'content' => 'Body']), 'input-root');
        $this->flushManager->flush();
        $this->em->clear();
        self::assertSame('A sufficiently long input title', $this->em->find(Article::class, 'input-root')->getTitle());
    }

    #[DataProvider('softVisibility')]
    public function testSoftVisibilityUsesConfiguration(string $visibility, int $count): void
    {
        foreach ([false, true] as $deleted) {
            $article = new SoftDeletableArticle();
            $article->setTitle($deleted ? 'Deleted' : 'Active');
            $article->setContent('Body');
            if ($deleted) {
                $article->setDeletedAt(new \DateTimeImmutable());
            }
            $this->em->persist($article);
        }
        $this->em->flush();
        $profile = new SoftDeleteProfile(['default_visibility' => $visibility]);
        $request = $this->stack($profile)->getCurrentRequest();
        $errors = new \AlexFigures\Symfony\Http\Error\ErrorMapper(new \AlexFigures\Symfony\Http\Error\ErrorBuilder(false));
        $parser = new QueryParser($this->registry, new PaginationConfig(), new SortingWhitelist($this->registry), new FilteringWhitelist($this->registry, $errors), $errors, new \AlexFigures\Symfony\Filter\Parser\FilterParser());
        $criteria = $parser->parse('soft-deletable-articles', $request);
        self::assertCount($count, $this->repository->findCollection('soft-deletable-articles', $criteria)->items);
    }
    public static function softVisibility(): iterable
    {
        yield ['exclude', 1];
        yield ['include', 2];
        yield ['only', 1];
    }

    public function testBooleanSoftDeleteUsesBooleanPredicateAndMutation(): void
    {
        foreach ([false, true] as $deleted) {
            $article = new SoftDeletableArticle();
            $article->setTitle($deleted ? 'Deleted' : 'Alive');
            $article->setContent('Body');
            $article->setDeleted($deleted);
            $this->em->persist($article);
        }
        $this->em->flush();
        $profile = new SoftDeleteProfile(['field' => 'deleted', 'strategy' => 'boolean']);
        $stack = $this->stack($profile);
        $criteria = new Criteria();
        foreach ($profile->hooks() as $hook) {
            if ($hook instanceof \AlexFigures\Symfony\Profile\Hook\QueryHook) {
                $hook->onParseQuery(ProfileContext::fromRequest($stack->getCurrentRequest()), $stack->getCurrentRequest(), $criteria);
            }
        }
        $items = $this->repository->findCollection('soft-deletable-articles', $criteria)->items;
        self::assertCount(1, $items);
        self::assertSame('Alive', $items[0]->getTitle());
        self::assertTrue((new ProfileWriteHooks($stack, $this->accessor))->softDelete($items[0], $this->registry->getByType('soft-deletable-articles')));
        $this->em->flush();
        self::assertSame([], $this->repository->findCollection('soft-deletable-articles', $criteria)->items);
    }

    #[DataProvider('deleteModes')]
    public function testSoftDeleteSemanticsPreserveOrRemoveRow(string $mode, bool $remains): void
    {
        $article = new SoftDeletableArticle();
        $article->setTitle('Delete');
        $article->setContent('Body');
        $this->em->persist($article);
        $this->em->flush();
        $id = $article->getId();
        $stack = $this->stack(new SoftDeleteProfile(['delete_semantics' => $mode]));
        $processor = new ValidatingDoctrineProcessor($this->managerRegistry, $this->registry, $this->accessor, $this->validator, $this->violationMapper, new SerializerEntityInstantiator($this->managerRegistry, $this->accessor), new RelationshipResolver($this->managerRegistry, $this->registry, $this->accessor), $this->flushManager, new ProfileWriteHooks($stack, $this->accessor));
        $processor->processDelete('soft-deletable-articles', $id);
        $this->flushManager->flush();
        $this->em->clear();
        $saved = $this->em->find(SoftDeletableArticle::class, $id);
        self::assertSame($remains, $saved !== null);
        if ($remains) {
            self::assertInstanceOf(\DateTimeImmutable::class, $saved->getDeletedAt());
        }
    }
    public static function deleteModes(): iterable
    {
        yield ['soft', true];
        yield ['hard', false];
    }
}
