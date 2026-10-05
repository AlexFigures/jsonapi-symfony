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
        // This DTO exposes a subset of Article's API attributes; select that shared field in both representations.
        $representation = new Criteria();
        $representation->fields = ['articles' => ['title']];
        $document = $builder->buildResource('articles', $view, $representation, $stack->getCurrentRequest());
        self::assertSame('Original content', $document['data']['attributes']['title']);
        self::assertSame('Original content', $repository->findCollection('articles', new Criteria())->items[0]->title);
        $stack->pop();
        self::assertInstanceOf(Article::class, $repository->findOne('articles', 'root', new Criteria()));
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
