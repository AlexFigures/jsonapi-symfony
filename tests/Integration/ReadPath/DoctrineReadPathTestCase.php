<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\ReadPath;

use AlexFigures\Symfony\Bridge\Doctrine\Read\DoctrineRepresentationPreloader;
use AlexFigures\Symfony\Filter\Ast\Comparison;
use AlexFigures\Symfony\Http\Document\DocumentBuilder;
use AlexFigures\Symfony\Http\Document\Fetch\RepresentationFetchPlanner;
use AlexFigures\Symfony\Http\Error\ErrorBuilder;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Exception\BadRequestException;
use AlexFigures\Symfony\Http\Link\LinkGenerator;
use AlexFigures\Symfony\Http\Safety\LimitsEnforcer;
use AlexFigures\Symfony\Http\Safety\RequestComplexityScorer;
use AlexFigures\Symfony\Profile\Builtin\RelationshipCountsProfile;
use AlexFigures\Symfony\Profile\ProfileContext;
use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Query\Pagination;
use AlexFigures\Symfony\Query\Sorting;
use AlexFigures\Symfony\Resource\Mapper\DefaultReadMapper;
use AlexFigures\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Tag;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\ORM\PersistentCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

abstract class DoctrineReadPathTestCase extends DoctrineIntegrationTestCase
{
    private function seedGraph(int $roots = 25, int $tags = 3): void
    {
        $related = [];
        for ($j = 0; $j < $tags; ++$j) {
            $tag = (new Tag())->setId(sprintf('tag-%04d', $j))->setName('Tag ' . $j);
            $this->em->persist($tag);
            $related[] = $tag;
        }
        for ($i = 0; $i < $roots; ++$i) {
            $author = (new Author())->setId(sprintf('author-%04d', $i))->setName('Author ' . $i)->setEmail('author' . $i . '@example.com');
            $article = (new Article())->setId(sprintf('article-%04d', $i))->setTitle('Article ' . $i)->setContent('Body')->setAuthor($author);
            foreach ($related as $tag) {
                $article->addTag($tag);
            }
            $this->em->persist($author);
            $this->em->persist($article);
        }
        $this->em->flush();
        $this->em->clear();
    }

    /** @param array<string, int> $limits
     * @param list<\AlexFigures\Symfony\Contract\Data\RelationshipBatchReaderInterface> $batchReaders
     */
    private function builder(string $mode = 'always', array $limits = [], ?\AlexFigures\Symfony\Contract\Data\ResourceRepository $repository = null, array $batchReaders = [], string $unplanned = 'legacy'): DocumentBuilder
    {
        $routes = new RouteCollection();
        foreach (['articles' => ['author', 'tags', 'secondaryAuthor', 'parentAuthor', 'secondaryTags', 'tertiaryTags'], 'authors' => ['articles', 'otherArticles', 'recentArticles', 'archivedArticles'], 'tags' => [], 'articles-with-special-tags' => ['author', 'tags', 'specialTags'], 'special-tags' => []] as $type => $relationships) {
            $routes->add('jsonapi.' . $type . '.index', new Route('/api/' . $type));
            $routes->add('jsonapi.' . $type . '.show', new Route('/api/' . $type . '/{id}'));
            foreach ($relationships as $relationship) {
                $routes->add('jsonapi.' . $type . '.related.' . $relationship, new Route('/api/' . $type . '/{id}/' . $relationship));
                $routes->add('jsonapi.' . $type . '.relationships.' . $relationship . '.show', new Route('/api/' . $type . '/{id}/relationships/' . $relationship));
            }
        }
        $errors = new ErrorMapper(new ErrorBuilder(false));
        $limits += ['included_max_resources' => 250, 'relationship_max_identifiers' => 10000];
        $preloader = new DoctrineRepresentationPreloader($this->managerRegistry, $this->registry, $this->accessor, new DefaultReadMapper(), new RepresentationFetchPlanner($mode), $errors, $limits, $repository, null, $batchReaders, $unplanned);
        return new DocumentBuilder($this->registry, $this->accessor, new LinkGenerator(new UrlGenerator($routes, new RequestContext())), $mode, new LimitsEnforcer($errors, new RequestComplexityScorer(), $limits), $preloader);
    }

    private function expandNativeGraph(): void
    {
        $root = $this->registry->getByType('articles');
        foreach (['secondaryAuthor' => 'author', 'parentAuthor' => 'author', 'secondaryTags' => 'tags', 'tertiaryTags' => 'tags'] as $name => $path) {
            $relationship = clone $root->relationships[$path];
            $relationship->name = $name;
            $relationship->propertyPath = $path;
            $root->relationships[$name] = $relationship;
        }
        $authors = $this->registry->getByType('authors');
        foreach (['otherArticles', 'recentArticles', 'archivedArticles'] as $name) {
            $relationship = clone $authors->relationships['articles'];
            $relationship->name = $name;
            $relationship->propertyPath = 'articles';
            $authors->relationships[$name] = $relationship;
        }
    }

    /** @return iterable<string, array{list<string>, bool}> */
    public static function graphShapes(): iterable
    {
        yield 'plain linkage' => [[], false];
        yield 'to-one include' => [['author'], false];
        yield 'to-many include' => [['tags'], false];
        yield 'sibling and nested include' => [['author.articles.tags', 'tags'], false];
        yield 'sparse attributes' => [[], true];
    }

    #[DataProvider('graphShapes')]
    public function testQueryShapeDoesNotGrowWithRootPageSize(array $includes, bool $sparse): void
    {
        $this->seedGraph();
        $this->expandNativeGraph();
        $counts = [];
        foreach ([5, 20] as $size) {
            $this->em->clear();
            $log = new DebugStack();
            $this->em->getConnection()->getConfiguration()->setSQLLogger($log);
            $criteria = new Criteria(new Pagination(1, $size));
            $criteria->include = $includes;
            if ($sparse) {
                $criteria->fields['articles'] = ['title'];
            }
            $slice = $this->repository->findCollection('articles', $criteria);
            $document = $this->builder(repository: new \AlexFigures\Symfony\Bridge\Symfony\Locator\ResourceRepositoryLocator([], $this->repository))->buildCollection('articles', $slice->items, $criteria, $slice, Request::create('/api/articles'));
            self::assertCount($size, $document['data']);
            self::assertSame(25, $document['meta']['total']);
            $counts[] = count($log->queries);
            foreach ($slice->items as $article) {
                self::assertInstanceOf(Article::class, $article);
                self::assertInstanceOf(PersistentCollection::class, $article->getTags());
                self::assertFalse($article->getTags()->isInitialized(), 'Identifier linkage must not initialize collections.');
            }
            if ($includes !== []) {
                $primary = array_column($document['data'], 'id');
                foreach ($document['included'] as $resource) {
                    self::assertFalse($resource['type'] === 'articles' && in_array($resource['id'], $primary, true), 'Primary resources cannot be duplicated in included.');
                }
            }
        }
        self::assertSame($counts[0], $counts[1], 'SQL count must depend on graph shape, not root count.');
        $budget = match ($includes) {
            [] => 12, ['author'] => 18, ['tags'] => 24, default => 32,
        };
        self::assertLessThanOrEqual($budget, $counts[1]);
        if ($sparse) {
            self::assertSame(2, $counts[1]);
        }
    }

    public function testRelatedCollectionRepresentationStaysWithinQueryBudget(): void
    {
        $this->seedGraph(25);
        $this->expandNativeGraph();
        $this->em->createQuery('UPDATE ' . Article::class . ' a SET a.author = :owner')->setParameter('owner', 'author-0000')->execute();
        $this->em->clear();
        $log = new DebugStack();
        $this->em->getConnection()->getConfiguration()->setSQLLogger($log);
        $criteria = new Criteria(new Pagination(1, 20));
        $handler = new \AlexFigures\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler($this->managerRegistry, $this->registry, $this->accessor, $this->flushManager, $this->repository);
        $slice = $handler->getRelatedCollection('authors', 'author-0000', 'articles', $criteria);
        $document = $this->builder(repository: $this->repository)->buildCollection('articles', $slice->items, $criteria, $slice, Request::create('/api/authors/author-0000/articles'));
        self::assertCount(20, $document['data']);
        self::assertLessThanOrEqual(15, count($log->queries));
    }

    public function testNativeGraphReadHookScopeIsEmbeddedWithoutVisibilityQueries(): void
    {
        $this->seedGraph(20);
        $hook = new class () implements \AlexFigures\Symfony\Profile\Hook\ReadHook {
            public function onBeforeFindCollection(ProfileContext $context, string $type, Criteria $criteria): void
            {
                if ($type === 'tags') {
                    $criteria->customConditions[] = static function (\Doctrine\ORM\QueryBuilder $query): void {
                        $query->andWhere('e.id = :visible')->setParameter('visible', 'tag-0000');
                    };
                }
            }
            public function onBeforeFindOne(ProfileContext $context, string $type, string $id, Criteria $criteria): void
            {
            }
        };
        $profile = new \AlexFigures\Symfony\Tests\Util\FakeProfile('urn:scope', [$hook]);
        $request = Request::create('/api/articles?include=tags');
        ProfileContext::store($request, new ProfileContext([], ['tags' => [$profile]]));
        $stack = new \Symfony\Component\HttpFoundation\RequestStack();
        $stack->push($request);
        $filters = new \AlexFigures\Symfony\Filter\Handler\Registry\FilterHandlerRegistry();
        $repository = new \AlexFigures\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository($this->managerRegistry, $this->registry, new \AlexFigures\Symfony\Filter\Compiler\Doctrine\DoctrineFilterCompiler(new \AlexFigures\Symfony\Filter\Operator\Registry(), $filters), $filters, new \AlexFigures\Symfony\Filter\Handler\Registry\SortHandlerRegistry(), new DefaultReadMapper(), requests: $stack);
        $criteria = new Criteria(new Pagination(1, 20));
        $criteria->include = ['tags'];
        $log = new DebugStack();
        $this->em->getConnection()->getConfiguration()->setSQLLogger($log);
        $slice = $repository->findCollection('articles', $criteria);
        $document = $this->builder(repository: $repository)->buildCollection('articles', $slice->items, $criteria, $slice, $request);
        self::assertCount(1, $document['included']);
        self::assertSame('tag-0000', $document['included'][0]['id']);
        foreach ($document['data'] as $resource) {
            self::assertSame([['type' => 'tags', 'id' => 'tag-0000']], $resource['relationships']['tags']['data']);
        }
        self::assertLessThanOrEqual(8, count($log->queries));
    }

    public function testInheritedNativeCapabilityNeverBypassesSubclassVisibility(): void
    {
        $this->seedGraph(5);
        $filters = new \AlexFigures\Symfony\Filter\Handler\Registry\FilterHandlerRegistry();
        $repository = new class ($this->managerRegistry, $this->registry, new \AlexFigures\Symfony\Filter\Compiler\Doctrine\DoctrineFilterCompiler(new \AlexFigures\Symfony\Filter\Operator\Registry(), $filters), $filters, new \AlexFigures\Symfony\Filter\Handler\Registry\SortHandlerRegistry(), new DefaultReadMapper()) extends \AlexFigures\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository {
            public function findCollection(string $type, Criteria $criteria): \AlexFigures\Symfony\Contract\Data\Slice
            {
                $criteria = clone $criteria;
                if ($type === 'tags') {
                    $criteria->customConditions[] = static function (\Doctrine\ORM\QueryBuilder $query): void {
                        $query->andWhere('e.id = :visible')->setParameter('visible', 'tag-0000');
                    };
                }
                return parent::findCollection($type, $criteria);
            }
        };
        self::assertNull($repository->collectionQuery('tags', new Criteria()));
        $criteria = new Criteria();
        $criteria->include = ['tags'];
        $slice = $repository->findCollection('articles', $criteria);
        $document = $this->builder(repository: $repository)->buildCollection('articles', $slice->items, $criteria, $slice, Request::create('/api/articles?include=tags'));
        self::assertCount(1, $document['included']);
        self::assertSame('tag-0000', $document['included'][0]['id']);
        foreach ($document['data'] as $resource) {
            self::assertSame([['type' => 'tags', 'id' => 'tag-0000']], $resource['relationships']['tags']['data']);
        }
    }

    public function testToManyFilterPagesDistinctRootsWithStableBoundaries(): void
    {
        $this->seedGraph();
        $ids = [];
        foreach ([1, 2] as $number) {
            $criteria = new Criteria(new Pagination($number, 20));
            $criteria->filter = new Comparison('tags.id', 'in', ['tag-0000', 'tag-0001']);
            $slice = $this->repository->findCollection('articles', $criteria);
            self::assertSame(25, $slice->totalItems);
            self::assertCount($number === 1 ? 20 : 5, $slice->items);
            foreach ($slice->items as $article) {
                self::assertInstanceOf(Article::class, $article);
                $ids[] = $article->getId();
            }
        }
        self::assertCount(25, array_unique($ids));
        self::assertSame(array_map(static fn (int $i): string => sprintf('article-%04d', $i), range(0, 24)), $ids);
    }

    public function testCollectionSortIsRejectedBeforeAnySql(): void
    {
        $log = new DebugStack();
        $this->em->getConnection()->getConfiguration()->setSQLLogger($log);
        $criteria = new Criteria();
        $criteria->sort = [new Sorting('tags.name', false)];
        try {
            $strict = new \AlexFigures\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository(
                $this->managerRegistry,
                $this->registry,
                new \AlexFigures\Symfony\Filter\Compiler\Doctrine\DoctrineFilterCompiler(new \AlexFigures\Symfony\Filter\Operator\Registry([]), new \AlexFigures\Symfony\Filter\Handler\Registry\FilterHandlerRegistry([])),
                new \AlexFigures\Symfony\Filter\Handler\Registry\FilterHandlerRegistry([]),
                new \AlexFigures\Symfony\Filter\Handler\Registry\SortHandlerRegistry(),
                new DefaultReadMapper(),
                'reject',
            );
            $strict->findCollection('articles', $criteria);
            self::fail('Expected explicit collection-sort rejection.');
        } catch (BadRequestException $exception) {
            self::assertSame(400, $exception->getStatusCode());
            self::assertSame([], $log->queries);
        }
    }

    public function testIncludeBudgetStopsBeforeHydratingLargeTargetCollection(): void
    {
        $this->seedGraph(1, 500);
        $criteria = new Criteria();
        $criteria->include = ['tags'];
        $slice = $this->repository->findCollection('articles', $criteria);
        $log = new DebugStack();
        $this->em->getConnection()->getConfiguration()->setSQLLogger($log);
        try {
            $this->builder(limits: ['included_max_resources' => 10])->buildCollection('articles', $slice->items, $criteria, $slice, Request::create('/api/articles'));
            self::fail('Expected early include budget rejection.');
        } catch (BadRequestException $exception) {
            self::assertSame(400, $exception->getStatusCode());
            self::assertCount(0, $this->em->getUnitOfWork()->getIdentityMap()[Tag::class] ?? []);
            self::assertLessThanOrEqual(2, count($log->queries));
            $bounded = array_filter($log->queries, static fn (array $query): bool => str_contains($query['sql'], 'LIMIT 11'));
            self::assertCount(1, $bounded);
        }
    }

    public function testLinkageHasIndependentBudgetAndDoesNotHydrateTargets(): void
    {
        $this->seedGraph(1, 30);
        $criteria = new Criteria();
        $slice = $this->repository->findCollection('articles', $criteria);
        try {
            $this->builder(limits: ['relationship_max_identifiers' => 10])->buildCollection('articles', $slice->items, $criteria, $slice, Request::create('/api/articles'));
            self::fail('Expected linkage budget rejection.');
        } catch (BadRequestException $exception) {
            self::assertSame(400, $exception->getStatusCode());
            self::assertCount(0, $this->em->getUnitOfWork()->getIdentityMap()[Tag::class] ?? []);
        }
    }

    public function testProfileCountsUseGroupedQueriesWithoutLoadingCollections(): void
    {
        $this->seedGraph();
        $builder = $this->builder('never');
        $counts = [];
        foreach ([5, 20] as $size) {
            $this->em->clear();
            $criteria = new Criteria(new Pagination(1, $size));
            $slice = $this->repository->findCollection('articles', $criteria);
            $request = Request::create('/api/articles');
            $profile = new RelationshipCountsProfile();
            ProfileContext::store($request, new ProfileContext([$profile->uri() => $profile]));
            $log = new DebugStack();
            $this->em->getConnection()->getConfiguration()->setSQLLogger($log);
            $document = $builder->buildCollection('articles', $slice->items, $criteria, $slice, $request);
            foreach ($document['data'] as $resource) {
                self::assertSame(3, $resource['relationships']['tags']['meta']['count']);
                self::assertArrayNotHasKey('data', $resource['relationships']['tags']);
            }
            $counts[] = count($log->queries);
        }
        self::assertSame([1, 1], $counts);
    }
    public function testAggregateSortHandlerDefinesMinimumRelatedValue(): void
    {
        $this->seedGraph();
        $firstTag = (new Tag())->setId('tag-first')->setName('AAA');
        $this->em->persist($firstTag);
        $last = $this->em->find(Article::class, 'article-0024');
        self::assertInstanceOf(Article::class, $last);
        $last->addTag($firstTag);
        $this->em->flush();
        $this->em->clear();
        $handler = new class () implements \AlexFigures\Symfony\Filter\Handler\SortHandlerInterface {
            public function supports(string $field): bool
            {
                return $field === 'tags.name';
            }
            public function getPriority(): int
            {
                return 0;
            }
            public function handle(string $field, bool $descending, object $queryBuilder): void
            {
                \assert($queryBuilder instanceof \Doctrine\ORM\QueryBuilder);
                $queryBuilder->addSelect('(SELECT MIN(t_min.name) FROM ' . Article::class . ' article_sort JOIN article_sort.tags t_min WHERE article_sort.id = e.id) AS HIDDEN tag_min');
                $queryBuilder->addOrderBy('tag_min', $descending ? 'DESC' : 'ASC');
            }
        };
        $criteria = new Criteria(new Pagination(1, 20));
        $criteria->sort = [new Sorting('tags.name', false)];
        $slice = $this->customRepository([$handler], 'reject')->findCollection('articles', $criteria);
        self::assertSame(25, $slice->totalItems);
        self::assertCount(20, $slice->items);
        self::assertInstanceOf(Article::class, $slice->items[0]);
        self::assertSame('article-0024', $slice->items[0]->getId());
    }

    public function testDtoProjectionUsesTheDistinctRootPageAndPreservesOrder(): void
    {
        $this->seedGraph();
        $metadata = $this->registry->getByType('articles');
        $metadata->readProjection = \AlexFigures\Symfony\Resource\Definition\ReadProjection::DTO;
        $metadata->viewClass = \AlexFigures\Symfony\Tests\Integration\Fixtures\Dto\ArticleViewDto::class;
        $metadata->fieldMap = ['id' => 'e.id', 'title' => 'e.title', 'content' => 'e.content', 'createdAt' => 'e.createdAt'];
        $criteria = new Criteria(new Pagination(1, 20));
        $criteria->filter = new Comparison('tags.id', 'in', ['tag-0000', 'tag-0001']);
        $slice = $this->repository->findCollection('articles', $criteria);
        self::assertSame(25, $slice->totalItems);
        self::assertCount(20, $slice->items);
        foreach ($slice->items as $i => $view) {
            self::assertInstanceOf(\AlexFigures\Symfony\Tests\Integration\Fixtures\Dto\ArticleViewDto::class, $view);
            self::assertSame(sprintf('article-%04d', $i), $view->id);
        }
    }

    public function testUuidArrayBindingAndProjectionIdentifierAlias(): void
    {
        $ids = [];
        for ($i = 0; $i < 3; ++$i) {
            $record = new \AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\TypedIdentifierRecord();
            $record->id = \Symfony\Component\Uid\Uuid::v4();
            $record->name = 'Record ' . $i;
            $ids[] = (string) $record->id;
            $this->em->persist($record);
        }
        $this->em->flush();
        $this->em->clear();
        $metadata = $this->registry->getByType('typed-records');
        $metadata->readProjection = \AlexFigures\Symfony\Resource\Definition\ReadProjection::DTO;
        $metadata->viewClass = TypedIdentifierView::class;
        $metadata->fieldMap = ['identifier' => 'e.id', 'name' => 'e.name'];
        $metadata->idPropertyPath = 'identifier';
        $criteria = new Criteria(new Pagination(1, 2));
        // An explicit sort avoids interpreting a view alias as an entity field.
        $criteria->sort = [new Sorting('name', false)];
        $slice = $this->repository->findCollection('typed-records', $criteria);
        self::assertSame(3, $slice->totalItems);
        self::assertCount(2, $slice->items);
        foreach ($slice->items as $view) {
            self::assertInstanceOf(TypedIdentifierView::class, $view);
            self::assertContains((string) $view->identifier, $ids);
        }
    }

    public function testAliasThroughJoinEntityUsesOneBatchedEdge(): void
    {
        $articleClass = \AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\ArticleWithSpecialTags::class;
        $tagClass = \AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\SpecialTag::class;
        $this->registry = new \AlexFigures\Symfony\Resource\Registry\ResourceRegistry([$articleClass, $tagClass, Tag::class, \AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\AuthorForSpecialTags::class]);
        $this->registry->getByType('articles-with-special-tags')->relationships['specialTags']->aliasPath = 'articleSpecialTags.specialTag';
        $tag = (new $tagClass())->setName('Alias tag');
        $this->em->persist($tag);
        for ($i = 0; $i < 20; ++$i) {
            $article = (new $articleClass())->setTitle('Article ' . $i)->setContent('Body');
            $join = (new \AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\ArticleWithSpecialTagsSpecialTag())->setSpecialTag($tag);
            $article->addArticleSpecialTag($join);
            $this->em->persist($article);
            $this->em->persist($join);
        }
        $this->em->flush();
        $this->em->clear();
        $criteria = new Criteria(new Pagination(1, 20));
        $criteria->fields['articles-with-special-tags'] = ['title', 'specialTags'];
        $criteria->include = ['specialTags'];
        $slice = $this->customRepository()->findCollection('articles-with-special-tags', $criteria);
        $log = new DebugStack();
        $this->em->getConnection()->getConfiguration()->setSQLLogger($log);
        $document = $this->builder()->buildCollection('articles-with-special-tags', $slice->items, $criteria, $slice, Request::create('/api/articles-with-special-tags'));
        self::assertCount(20, $document['data']);
        self::assertCount(1, $document['included']);
        foreach ($document['data'] as $resource) {
            self::assertSame([['type' => 'special-tags', 'id' => $tag->getId()]], $resource['relationships']['specialTags']['data']);
        }
        self::assertSame(3, count($log->queries));
    }

    public function testSharedIncludesConsumeDistinctBudgetAndStateIsLocalToBuild(): void
    {
        $this->seedGraph(20, 3);
        $criteria = new Criteria(new Pagination(1, 20));
        $criteria->include = ['tags'];
        $slice = $this->repository->findCollection('articles', $criteria);
        $builder = $this->builder(limits: ['included_max_resources' => 3]);
        for ($i = 0; $i < 2; ++$i) {
            $document = $builder->buildCollection('articles', $slice->items, $criteria, $slice, Request::create('/api/articles'));
            self::assertCount(3, $document['included']);
        }
    }

    public function testWriteRepresentationUsesFlushedRelationshipSnapshot(): void
    {
        $this->seedGraph(1, 3);
        $criteria = new Criteria();
        $slice = $this->repository->findCollection('articles', $criteria);
        $builder = $this->builder(limits: ['relationship_max_identifiers' => 1]);
        $request = Request::create('/api/articles/article-0000', 'PATCH');
        $request->attributes->set('_jsonapi_representation_flushed', true);
        // Successful write responses do not acquire a new rejection after commit.
        $document = $builder->buildResource('articles', $slice->items[0], $criteria, $request);
        self::assertCount(3, $document['data']['relationships']['tags']['data']);
        $get = $this->builder()->buildResource('articles', $slice->items[0], $criteria, Request::create('/api/articles/article-0000'));
        self::assertEquals($get['data'], $document['data']);
    }


    public function testMappedCollectionOrderIsPreservedWithoutFetchJoin(): void
    {
        $this->seedGraph(1, 3);
        $association = $this->em->getClassMetadata(Article::class)->getAssociationMapping('tags');
        self::assertInstanceOf(\Doctrine\ORM\Mapping\ManyToManyOwningSideMapping::class, $association);
        $association->orderBy = ['name' => 'desc'];
        $criteria = new Criteria();
        $slice = $this->repository->findCollection('articles', $criteria);
        $document = $this->builder()->buildCollection('articles', $slice->items, $criteria, $slice, Request::create('/api/articles'));
        self::assertSame(['tag-0002', 'tag-0001', 'tag-0000'], array_column($document['data'][0]['relationships']['tags']['data'], 'id'));
    }

    public function testIncludeRemainsLoadedWhenSparseFieldsOmitItsLinkage(): void
    {
        $this->seedGraph(1, 3);
        $criteria = new Criteria();
        $criteria->fields['articles'] = ['title'];
        $criteria->include = ['tags'];
        $slice = $this->repository->findCollection('articles', $criteria);
        $document = $this->builder()->buildCollection('articles', $slice->items, $criteria, $slice, Request::create('/api/articles'));
        self::assertArrayNotHasKey('relationships', $document['data'][0]);
        self::assertCount(3, $document['included']);
    }

    public function testDtoProjectionWithoutExplicitMapUsesScalarFields(): void
    {
        $this->seedGraph(1, 1);
        $metadata = $this->registry->getByType('articles');
        $metadata->readProjection = \AlexFigures\Symfony\Resource\Definition\ReadProjection::DTO;
        $metadata->viewClass = \AlexFigures\Symfony\Tests\Integration\Fixtures\Dto\ArticleViewDto::class;
        $metadata->fieldMap = [];
        $slice = $this->repository->findCollection('articles', new Criteria());
        self::assertInstanceOf(\AlexFigures\Symfony\Tests\Integration\Fixtures\Dto\ArticleViewDto::class, $slice->items[0]);
        self::assertSame('article-0000', $slice->items[0]->id);
        $one = $this->repository->findOne('articles', 'article-0000', new Criteria());
        self::assertInstanceOf(\AlexFigures\Symfony\Tests\Integration\Fixtures\Dto\ArticleViewDto::class, $one);
        self::assertSame('Article 0', $one->title);
    }

    public function testIncludeRetainsFullLinkageUnderNeverPolicy(): void
    {
        $this->seedGraph(1, 3);
        $criteria = new Criteria();
        $criteria->include = ['tags'];
        $slice = $this->repository->findCollection('articles', $criteria);
        $document = $this->builder('never')->buildCollection('articles', $slice->items, $criteria, $slice, Request::create('/api/articles'));
        self::assertCount(3, $document['included']);
        self::assertCount(3, $document['data'][0]['relationships']['tags']['data']);
        self::assertArrayNotHasKey('data', $document['data'][0]['relationships']['author']);
    }

    /** @param list<\AlexFigures\Symfony\Filter\Handler\SortHandlerInterface> $handlers */
    private function customRepository(array $handlers = [], string $policy = 'legacy'): \AlexFigures\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository
    {
        $filters = new \AlexFigures\Symfony\Filter\Handler\Registry\FilterHandlerRegistry([]);
        return new \AlexFigures\Symfony\Bridge\Doctrine\Repository\GenericDoctrineRepository(
            $this->managerRegistry,
            $this->registry,
            new \AlexFigures\Symfony\Filter\Compiler\Doctrine\DoctrineFilterCompiler(new \AlexFigures\Symfony\Filter\Operator\Registry([]), $filters),
            $filters,
            new \AlexFigures\Symfony\Filter\Handler\Registry\SortHandlerRegistry($handlers),
            new DefaultReadMapper(),
            $policy
        );
    }

    public function testRelationshipPagesAndLinkageRemainInSql(): void
    {
        $this->seedGraph(30);
        $this->em->createQuery('UPDATE ' . Article::class . ' a SET a.author = :owner')->setParameter('owner', 'author-0000')->execute();
        $this->em->clear();
        $handler = new \AlexFigures\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler($this->managerRegistry, $this->registry, $this->accessor, $this->flushManager, $this->repository);
        $owner = $this->em->find(Author::class, 'author-0000');
        self::assertNotNull($owner);
        $related = $handler->getRelatedCollection('authors', 'author-0000', 'articles', new Criteria(new Pagination(2, 20)));
        self::assertCount(10, $related->items);
        self::assertSame(30, $related->totalItems);
        self::assertSame('article-0020', $related->items[0]->getId());
        $this->em->clear();
        $ids = $handler->getToManyIds('authors', 'author-0000', 'articles', new Pagination(2, 20));
        self::assertSame(30, $ids->totalItems);
        self::assertCount(10, $ids->ids);
        self::assertSame('article-0020', $ids->ids[0]);
        foreach ($this->em->getUnitOfWork()->getIdentityMap() as $class => $models) {
            self::assertNotSame(Article::class, $class, 'Scalar linkage must not hydrate articles.');
        }
    }

    public function testRepositoryScopeAppliesBeforeGraphReadsAndPagination(): void
    {
        $this->seedGraph(30);
        $this->em->createQuery('UPDATE ' . Article::class . ' a SET a.author = :owner')->setParameter('owner', 'author-0000')->execute();
        $this->em->clear();
        $scoped = new class ($this->repository) implements \AlexFigures\Symfony\Contract\Data\ResourceRepository {
            public function __construct(private \AlexFigures\Symfony\Contract\Data\ResourceRepository $inner)
            {
            }
            public function findCollection(string $type, Criteria $criteria): \AlexFigures\Symfony\Contract\Data\Slice
            {
                $criteria = clone $criteria;
                if ($type === 'articles') {
                    $criteria->customConditions[] = static function (\Doctrine\ORM\QueryBuilder $query): void {
                        $query->innerJoin($query->getRootAliases()[0] . '.author', 'scope_owner')->andWhere('scope_owner.id = :scopeOwner')->setParameter('scopeOwner', 'author-0000');
                        $query->andWhere($query->getRootAliases()[0] . '.id >= :visible')->setParameter('visible', 'article-0020');
                    };
                }
                return $this->inner->findCollection($type, $criteria);
            }
            public function findOne(string $type, string $id, Criteria $criteria): ?object
            {
                return $this->inner->findOne($type, $id, $criteria);
            }
            public function findRelated(string $type, string $relationship, array $identifiers): iterable
            {
                return $this->inner->findRelated($type, $relationship, $identifiers);
            }
        };
        $handler = new \AlexFigures\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler($this->managerRegistry, $this->registry, $this->accessor, $this->flushManager, $scoped);
        $page = $handler->getRelatedCollection('authors', 'author-0000', 'articles', new Criteria(new Pagination(1, 5)));
        self::assertSame(10, $page->totalItems);
        self::assertSame('article-0020', $page->items[0]->getId());
        $ids = $handler->getToManyIds('authors', 'author-0000', 'articles', new Pagination(1, 5));
        self::assertSame(10, $ids->totalItems);
        self::assertSame('article-0020', $ids->ids[0]);
        $criteria = new Criteria();
        $criteria->include = ['articles'];
        $owner = $this->em->find(Author::class, 'author-0000');
        self::assertNotNull($owner);
        $document = $this->builder('always', [], $scoped)->buildResource('authors', $owner, $criteria, Request::create('/api/authors/author-0000?include=articles'));
        self::assertCount(10, $document['data']['relationships']['articles']['data']);
        self::assertSame('article-0020', $document['data']['relationships']['articles']['data'][0]['id']);
        $articles = array_filter($document['included'], static fn (array $row): bool => $row['type'] === 'articles');
        self::assertCount(10, $articles);
        foreach ($articles as $article) {
            self::assertGreaterThanOrEqual('article-0020', $article['id']);
        }
    }

    public function testStrictFallbackRejectsComputedRelationshipBeforeCollectionAccess(): void
    {
        $this->seedGraph(1);
        $this->registry->getByType('articles')->relationships['tags']->propertyPath = 'computedTags';
        $model = $this->repository->findOne('articles', 'article-0000', new Criteria());
        self::assertInstanceOf(Article::class, $model);
        self::assertInstanceOf(PersistentCollection::class, $model->getTags());
        try {
            $this->builder('always', [], null, [], 'reject')->buildResource('articles', $model, new Criteria(), Request::create('/api/articles/article-0000'));
            self::fail('Unplanned getter must not run.');
        } catch (BadRequestException $error) {
            self::assertStringContainsString('no bounded fetch plan', $error->getMessage());
            self::assertFalse($model->getTags()->isInitialized());
        }
    }

    public function testComputedRelationshipLoadsOneBatchAndReceivesBudgets(): void
    {
        $this->seedGraph(20);
        $this->registry->getByType('articles')->relationships['tags']->propertyPath = 'computedTags';
        $reader = new class ($this->repository) implements \AlexFigures\Symfony\Contract\Data\RelationshipBatchReaderInterface {
            public int $calls = 0;
            public function __construct(private \AlexFigures\Symfony\Contract\Data\ResourceRepository $repository)
            {
            }
            public function supports(string $type, string $relationship): bool
            {
                return $type === 'articles' && $relationship === 'tags';
            }
            public function read(\AlexFigures\Symfony\Query\Fetch\RelationshipReadRequirements $requirements, Criteria $criteria, Request $request): \AlexFigures\Symfony\Query\Fetch\RelationshipReadMap
            {
                ++$this->calls;
                if ($requirements->remainingIncluded !== 3) {
                    throw new \LogicException('Expected the declared model budget.');
                }
                $criteria->pagination = new Pagination(1, $requirements->remainingIncluded + 1);
                $slice = $this->repository->findCollection('tags', $criteria);
                if ($slice->totalItems > $requirements->remainingIncluded) {
                    throw new BadRequestException('Probe exceeded budget before hydration of more targets.');
                }
                $map = new \AlexFigures\Symfony\Query\Fetch\RelationshipReadMap();
                foreach ($requirements->ownerIds as $ownerId) {
                    $ids = [];
                    foreach ($slice->items as $tag) {
                        if (!$tag instanceof Tag) {
                            throw new \LogicException('Expected tag.');
                        }
                        $ids[] = ['type' => 'tags', 'id' => $tag->getId()];
                        $map->remember('tags', $tag->getId(), $tag);
                    }
                    $map->put('articles', $ownerId, 'tags', $ids);
                }
                return $map;
            }
        };
        $criteria = new Criteria(new Pagination(1, 20));
        $criteria->include = ['tags'];
        $slice = $this->repository->findCollection('articles', $criteria);
        $document = $this->builder('when_included', ['included_max_resources' => 3], null, [$reader], 'reject')->buildCollection('articles', $slice->items, $criteria, $slice, Request::create('/api/articles?include=tags'));
        self::assertSame(1, $reader->calls);
        self::assertCount(3, $document['included']);
        foreach ($slice->items as $model) {
            self::assertInstanceOf(Article::class, $model);
            self::assertInstanceOf(PersistentCollection::class, $model->getTags());
            self::assertFalse($model->getTags()->isInitialized());
        }
    }

    public function testDocumentHookDeclaresAndConsumesBatchModelsWithoutIncludingThem(): void
    {
        $this->seedGraph(20);
        $hook = new class () implements \AlexFigures\Symfony\Profile\Hook\DocumentHook, \AlexFigures\Symfony\Profile\Hook\RelationshipFetchRequirementsHookInterface {
            public int $calls = 0;
            public function relationshipReads(\AlexFigures\Symfony\Resource\Metadata\ResourceMetadata $metadata): array
            {
                return $metadata->type === 'articles' ? ['tags' => 'models'] : [];
            }
            public function onTopLevelLinks(ProfileContext $context, array &$links, Request $request): void
            {
            }
            public function onTopLevelMeta(ProfileContext $context, array &$meta): void
            {
            }
            public function onResourceRelationships(ProfileContext $context, \AlexFigures\Symfony\Resource\Metadata\ResourceMetadata $metadata, array &$relationshipsPayload, object $model): void
            {
                if (!$model instanceof Article) {
                    return;
                }
                if (count($context->relationshipReads?->related('articles', $model->getId(), 'tags') ?? []) !== 3) {
                    throw new \LogicException('Required batch models missing.');
                }
                ++$this->calls;
            }
        };
        $profile = new \AlexFigures\Symfony\Tests\Util\FakeProfile('https://example.test/fetch', [$hook]);
        $request = Request::create('/api/articles');
        ProfileContext::store($request, new ProfileContext([$profile->uri() => $profile], []));
        $criteria = new Criteria(new Pagination(1, 20));
        $slice = $this->repository->findCollection('articles', $criteria);
        $log = new DebugStack();
        $this->em->getConnection()->getConfiguration()->setSQLLogger($log);
        $document = $this->builder('never')->buildCollection('articles', $slice->items, $criteria, $slice, $request);
        self::assertSame(20, $hook->calls);
        self::assertArrayNotHasKey('included', $document);
        self::assertLessThanOrEqual(3, count($log->queries));
    }

    public function testRelatedFiltersAndSortingApplyBeforeRootPagination(): void
    {
        $this->seedGraph(30);
        $this->em->createQuery('UPDATE ' . Article::class . ' a SET a.author = :owner')->setParameter('owner', 'author-0000')->execute();
        $this->em->clear();
        $criteria = new Criteria(new Pagination(2, 5));
        $criteria->filter = new Comparison('id', 'in', array_map(static fn (int $i): string => sprintf('article-%04d', $i), range(15, 29)));
        $criteria->sort = [new Sorting('id', true)];
        $handler = new \AlexFigures\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler($this->managerRegistry, $this->registry, $this->accessor, $this->flushManager, $this->repository);
        $slice = $handler->getRelatedCollection('authors', 'author-0000', 'articles', $criteria);
        self::assertSame(15, $slice->totalItems);
        self::assertSame(['article-0024', 'article-0023', 'article-0022', 'article-0021', 'article-0020'], array_map(static fn (object $model): string => $model instanceof Article ? $model->getId() : '', $slice->items));
        $metadata = $this->registry->getByType('articles');
        $metadata->readProjection = \AlexFigures\Symfony\Resource\Definition\ReadProjection::DTO;
        $metadata->viewClass = \AlexFigures\Symfony\Tests\Integration\Fixtures\Dto\ArticleViewDto::class;
        $metadata->fieldMap = ['id' => 'e.id', 'title' => 'e.title', 'content' => 'e.content', 'createdAt' => 'e.createdAt'];
        $dto = $handler->getRelatedCollection('authors', 'author-0000', 'articles', $criteria);
        self::assertSame(15, $dto->totalItems);
        self::assertInstanceOf(\AlexFigures\Symfony\Tests\Integration\Fixtures\Dto\ArticleViewDto::class, $dto->items[0]);
        self::assertSame('article-0024', $dto->items[0]->id);
    }

    public function testGraphQueryHooksUseTargetTypeAndRetainRequestIdentity(): void
    {
        $this->seedGraph(1);
        $errors = new ErrorMapper(new ErrorBuilder(false));
        $parser = new \AlexFigures\Symfony\Http\Request\QueryParser($this->registry, new \AlexFigures\Symfony\Http\Request\PaginationConfig(), new \AlexFigures\Symfony\Http\Request\SortingWhitelist($this->registry), new \AlexFigures\Symfony\Http\Request\FilteringWhitelist($this->registry, $errors), $errors, new \AlexFigures\Symfony\Filter\Parser\FilterParser());
        $hook = new class () implements \AlexFigures\Symfony\Profile\Hook\QueryHook {
            public function onParseQuery(ProfileContext $context, Request $request, Criteria $criteria): void
            {
                if ($request->attributes->get('type') === 'articles') {
                    if ($request->headers->get('X-Identity') !== 'limited') {
                        throw new \LogicException('Identity lost.');
                    }
                    $criteria->customConditions[] = static function (\Doctrine\ORM\QueryBuilder $query): void {
                        $query->andWhere('1 = 0');
                    };
                }
            }
        };
        $profile = new \AlexFigures\Symfony\Tests\Util\FakeProfile('https://example.test/scope', [$hook]);
        $request = Request::create('/api/authors/author-0000/relationships/articles?filter[unknown]=bad');
        $request->attributes->set('type', 'authors');
        $request->headers->set('X-Identity', 'limited');
        ProfileContext::store($request, new ProfileContext([$profile->uri() => $profile]));
        $stack = new \Symfony\Component\HttpFoundation\RequestStack();
        $stack->push($request);
        $handler = new \AlexFigures\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler($this->managerRegistry, $this->registry, $this->accessor, $this->flushManager, $this->repository, $parser, $stack);
        self::assertSame([], $handler->getToManyIds('authors', 'author-0000', 'articles')->ids);
        self::assertSame('authors', $request->attributes->get('type'));
        $criteria = new Criteria();
        $criteria->include = ['articles'];
        $owner = $this->em->find(Author::class, 'author-0000');
        self::assertNotNull($owner);
        $loader = new DoctrineRepresentationPreloader($this->managerRegistry, $this->registry, $this->accessor, new DefaultReadMapper(), new RepresentationFetchPlanner('always'), $errors, [], $this->repository, $parser);
        $reads = $loader->preload('authors', [$owner], $criteria, $request);
        self::assertSame([], $reads->identifiers('authors', 'author-0000', 'articles'));
        self::assertSame([], $reads->related('authors', 'author-0000', 'articles'));
    }

    public function testRelationshipEndpointRetainsMappedOrderBy(): void
    {
        $this->seedGraph(1, 3);
        $association = $this->em->getClassMetadata(Article::class)->getAssociationMapping('tags');
        $association->orderBy = ['name' => 'DESC'];
        $handler = new \AlexFigures\Symfony\Bridge\Doctrine\Relationship\GenericDoctrineRelationshipHandler($this->managerRegistry, $this->registry, $this->accessor, $this->flushManager, $this->repository);
        $slice = $handler->getRelatedCollection('articles', 'article-0000', 'tags', new Criteria(new Pagination(1, 2)));
        self::assertInstanceOf(Tag::class, $slice->items[0]);
        self::assertSame('Tag 2', $slice->items[0]->getName());
        $ids = $handler->getToManyIds('articles', 'article-0000', 'tags', new Pagination(2, 2));
        self::assertSame(['tag-0000'], $ids->ids);
        self::assertSame(3, $ids->totalItems);
    }

}
