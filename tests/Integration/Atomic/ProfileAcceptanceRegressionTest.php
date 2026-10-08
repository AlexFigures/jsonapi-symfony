<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Integration\Atomic;

use AlexFigures\JsonApi\Bridge\Doctrine\Instantiator\SerializerEntityInstantiator;
use AlexFigures\JsonApi\Bridge\Doctrine\Persister\ValidatingDoctrineProcessor;
use AlexFigures\JsonApi\Bridge\Doctrine\Profile\ProfileWriteHooks;
use AlexFigures\JsonApi\Contract\Data\ChangeSet;
use AlexFigures\JsonApi\Http\Request\FilteringWhitelist;
use AlexFigures\JsonApi\Http\Request\PaginationConfig;
use AlexFigures\JsonApi\Http\Request\QueryParser;
use AlexFigures\JsonApi\Http\Request\SortingWhitelist;
use AlexFigures\JsonApi\Profile\Builtin\AuditTrailProfile;
use AlexFigures\JsonApi\Profile\Builtin\RelationshipCountsProfile;
use AlexFigures\JsonApi\Profile\Builtin\SoftDeleteProfile;
use AlexFigures\JsonApi\Profile\ProfileContext;
use AlexFigures\JsonApi\Query\Criteria;
use AlexFigures\JsonApi\Resource\Relationship\RelationshipResolver;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\SoftDeletableArticle;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class ProfileAcceptanceRegressionTest extends DoctrineAtomicTestCase
{
    public function testPerTypeDefaultProfileRunsWithoutAcceptHeader(): void
    {
        $request = Request::create('/api/articles');
        $request->headers->remove('Accept');
        ProfileContext::store($request, new ProfileContext([], ['articles' => [new RelationshipCountsProfile()]]));
        $article = new Article();
        $article->setTitle('Example');
        $article->setContent('Example');
        $article->onPrePersist();
        $document = $this->documentBuilder->buildResource('articles', $article, new Criteria(), $request);
        self::assertSame(0, $document['data']['relationships']['tags']['meta']['count']);
        self::assertNull($request->headers->get('Accept'));
    }

    public function testNegotiatedQueryHookFiltersCollectionAndItemReads(): void
    {
        $deletedId = null;
        foreach ([false, true] as $deleted) {
            $article = new SoftDeletableArticle();
            $article->setTitle($deleted ? 'Archived' : 'Active');
            $article->setContent('Example');
            if ($deleted) {
                $article->setDeletedAt(new \DateTimeImmutable());
                $deletedId = $article->getId();
            }
            $this->em->persist($article);
        }
        $this->em->flush();
        $this->em->clear();
        $request = Request::create('/api/soft-deletable-articles');
        $profile = new SoftDeleteProfile();
        ProfileContext::store($request, new ProfileContext([$profile->uri() => $profile]));
        $parser = new QueryParser($this->registry, new PaginationConfig(), new SortingWhitelist($this->registry), new FilteringWhitelist($this->registry, $this->errorMapper), $this->errorMapper, new \AlexFigures\JsonApi\Filter\Parser\FilterParser());
        $criteria = $parser->parse('soft-deletable-articles', $request);
        $items = $this->repository->findCollection('soft-deletable-articles', $criteria)->items;
        self::assertCount(1, $items);
        self::assertSame('Active', $items[0]->getTitle());
        self::assertNull($this->repository->findOne('soft-deletable-articles', $deletedId, $criteria));
    }

    public function testAuditWriteHookPersistsTimestampBeforeFlush(): void
    {
        $request = Request::create('/api/auditable-products', 'POST');
        $profile = new AuditTrailProfile();
        ProfileContext::store($request, new ProfileContext([], ['auditable-products' => [$profile]]));
        $stack = new RequestStack();
        $stack->push($request);
        $processor = new ValidatingDoctrineProcessor($this->managerRegistry, $this->registry, $this->accessor, $this->validator, $this->violationMapper, new SerializerEntityInstantiator($this->managerRegistry, $this->accessor), new RelationshipResolver($this->managerRegistry, $this->registry, $this->accessor), $this->flushManager, new ProfileWriteHooks($stack, $this->accessor));
        $product = $this->transactionManager->transactional(fn () => $processor->processCreate('auditable-products', new ChangeSet(['name' => 'Example', 'price' => '10.00'])));
        $product->setUpdatedAt(new \DateTimeImmutable('2000-01-01T00:00:00Z'));
        $this->em->flush();
        $id = $product->getId();
        $this->transactionManager->transactional(fn () => $processor->processUpdate('auditable-products', $id, new ChangeSet(['name' => 'Updated'])));
        $this->em->clear();
        $saved = $this->repository->findOne('auditable-products', $id, new Criteria());
        self::assertSame('Updated', $saved->getName());
        self::assertGreaterThan(new \DateTimeImmutable('2020-01-01'), $saved->getUpdatedAt());
    }
}
