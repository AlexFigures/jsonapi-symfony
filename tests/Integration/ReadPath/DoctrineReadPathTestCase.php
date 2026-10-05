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

    /** @param array<string, int> $limits */
    private function builder(string $mode = 'always', array $limits = []): DocumentBuilder
    {
        $routes = new RouteCollection();
        foreach (['articles' => ['author', 'tags'], 'authors' => ['articles'], 'tags' => [], 'articles-with-special-tags' => ['author', 'tags', 'specialTags'], 'special-tags' => []] as $type => $relationships) {
            $routes->add('jsonapi.' . $type . '.index', new Route('/api/' . $type));
            $routes->add('jsonapi.' . $type . '.show', new Route('/api/' . $type . '/{id}'));
            foreach ($relationships as $relationship) {
                $routes->add('jsonapi.' . $type . '.related.' . $relationship, new Route('/api/' . $type . '/{id}/' . $relationship));
                $routes->add('jsonapi.' . $type . '.relationships.' . $relationship . '.show', new Route('/api/' . $type . '/{id}/relationships/' . $relationship));
            }
        }
        $errors = new ErrorMapper(new ErrorBuilder(false));
        $limits += ['included_max_resources' => 250, 'relationship_max_identifiers' => 10000];
        $preloader = new DoctrineRepresentationPreloader($this->managerRegistry, $this->registry, $this->accessor, new DefaultReadMapper(), new RepresentationFetchPlanner($mode), $errors, $limits);
        return new DocumentBuilder($this->registry, $this->accessor, new LinkGenerator(new UrlGenerator($routes, new RequestContext())), $mode, new LimitsEnforcer($errors, new RequestComplexityScorer(), $limits), $preloader);
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
            $document = $this->builder()->buildCollection('articles', $slice->items, $criteria, $slice, Request::create('/api/articles'));
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
        self::assertLessThanOrEqual(20, $counts[1]);
        if ($sparse) {
            self::assertSame(2, $counts[1]);
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

}
