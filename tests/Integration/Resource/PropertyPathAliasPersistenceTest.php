<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Resource;

use AlexFigures\Symfony\Bridge\Doctrine\Instantiator\SerializerEntityInstantiator;
use AlexFigures\Symfony\Bridge\Doctrine\Persister\ValidatingDoctrineProcessor;
use AlexFigures\Symfony\Contract\Data\ChangeSet;
use AlexFigures\Symfony\Http\Validation\ConstraintViolationMapper;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistry;
use AlexFigures\Symfony\Resource\Relationship\RelationshipResolver;
use AlexFigures\Symfony\Tests\Integration\DoctrineIntegrationTestCase;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\ArticleWithSpecialTags;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\ArticleWithSpecialTagsSpecialTag;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Author;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\AuthorForSpecialTags;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Category;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\CategorySynonym;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Comment;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Product;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\SpecialTag;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\Tag;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\TypeTestEntity;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\User;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Integration test for PropertyPath aliases in relationship persistence.
 *
 * This test verifies that relationships with propertyPath aliases work correctly
 * for persistence operations (CREATE/UPDATE) while maintaining the separation between:
 * - propertyPath: Real Doctrine property path for persistence
 * - aliasPath: API alias path for filtering/sorting/includes
 */
final class PropertyPathAliasPersistenceTest extends DoctrineIntegrationTestCase
{
    protected function getDatabaseUrl(): string
    {
        $url = $_ENV['DATABASE_URL_POSTGRES'] ?? 'postgresql://jsonapi:secret@postgres:5432/jsonapi_test?serverVersion=16&charset=utf8';
        assert(is_string($url));
        return $url;
    }

    protected function getPlatform(): string
    {
        return 'postgresql';
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create a new registry with all entities including our test ones
        $this->registry = new ResourceRegistry([
            Article::class,
            Author::class,
            Category::class,
            CategorySynonym::class,
            Comment::class,
            Tag::class,
            Product::class,
            TypeTestEntity::class,
            User::class,
            // Add our test entities
            ArticleWithSpecialTags::class,
            AuthorForSpecialTags::class,
            SpecialTag::class,
        ]);

        // Create dependencies for the processor
        $instantiator = new SerializerEntityInstantiator(
            $this->managerRegistry,
            PropertyAccess::createPropertyAccessor()
        );
        $relationshipResolver = new RelationshipResolver(
            $this->managerRegistry,
            $this->registry,
            PropertyAccess::createPropertyAccessor()
        );

        // Create a new validating processor with the updated registry
        $this->validatingProcessor = new ValidatingDoctrineProcessor(
            $this->managerRegistry,
            $this->registry,
            $this->accessor,
            $this->validator,
            $this->violationMapper,
            $instantiator,
            $relationshipResolver,
            $this->flushManager,
        );

        // Create test data
        $this->createTestData();
    }

    private function createTestData(): void
    {
        // Create author for special tags
        $author = new AuthorForSpecialTags();
        $author->setName('Test Author');
        $author->setEmail('author@example.com');
        $this->em->persist($author);

        // Create special tags
        $tag1 = new SpecialTag();
        $tag1->setName('PHP');
        $tag1->setCategory('language');
        $this->em->persist($tag1);

        $tag2 = new SpecialTag();
        $tag2->setName('Symfony');
        $tag2->setCategory('framework');
        $this->em->persist($tag2);

        $this->em->flush();
        $this->em->clear();
    }

    /**
     * Test creating a resource with aliased relationship.
     *
     * This verifies that the processor uses the correct propertyPath (not aliasPath)
     * for persistence operations.
     */
    public function testCreateResourceWithAliasedRelationship(): void
    {
        // Get tag IDs
        $phpTag = $this->em->getRepository(SpecialTag::class)->findOneBy(['name' => 'PHP']);
        $symfonyTag = $this->em->getRepository(SpecialTag::class)->findOneBy(['name' => 'Symfony']);
        $author = $this->em->getRepository(AuthorForSpecialTags::class)->findOneBy(['name' => 'Test Author']);

        $this->assertNotNull($phpTag);
        $this->assertNotNull($symfonyTag);
        $this->assertNotNull($author);

        // Create changeset with aliased relationship
        $changes = new ChangeSet(
            attributes: [
                'title' => 'Test Article',
                'content' => 'Test content',
                'status' => 'published'
            ],
            relationships: [
                'author' => [
                    'data' => ['type' => 'authors-for-special-tags', 'id' => $author->getId()]
                ],
                'specialTags' => [ // This uses the alias!
                    'data' => [
                        ['type' => 'special-tags', 'id' => $phpTag->getId()],
                        ['type' => 'special-tags', 'id' => $symfonyTag->getId()]
                    ]
                ]
            ]
        );



        // Process creation - this should work without errors
        $article = $this->validatingProcessor->processCreate('articles-with-special-tags', $changes);

        $this->assertInstanceOf(ArticleWithSpecialTags::class, $article);
        $this->assertSame('Test Article', $article->getTitle());
        $this->assertSame($author->getId(), $article->getAuthor()->getId());

        // Verify the relationship was persisted correctly
        // The processor should have used the real propertyPath (specialTags)
        // not the aliasPath (articleSpecialTags.specialTag)
        $this->em->flush();
        $this->em->clear();

        // Reload and verify
        $reloadedArticle = $this->em->find(ArticleWithSpecialTags::class, $article->getId());
        $this->assertNotNull($reloadedArticle);

        // Verify the relationship was persisted correctly
        $specialTags = $reloadedArticle->getSpecialTags();
        $this->assertCount(2, $specialTags);

        // Verify the tags are accessible through the alias path
        $specialTags = $reloadedArticle->getSpecialTags();
        $this->assertCount(2, $specialTags);

        $tagNames = array_map(fn ($tag) => $tag->getName(), $specialTags->toArray());
        $this->assertContains('PHP', $tagNames);
        $this->assertContains('Symfony', $tagNames);
    }

    /**
     * Test updating a resource with aliased relationship.
     *
     * This is the critical test that verifies the fix for the persistence issue.
     * Previously, using propertyPath as the persistence path would break relationship updates.
     */
    public function testUpdateResourceWithAliasedRelationship(): void
    {
        // First create an article
        $author = $this->em->getRepository(AuthorForSpecialTags::class)->findOneBy(['name' => 'Test Author']);
        $phpTag = $this->em->getRepository(SpecialTag::class)->findOneBy(['name' => 'PHP']);

        $this->assertNotNull($author);
        $this->assertNotNull($phpTag);

        $createChanges = new ChangeSet(
            attributes: [
                'title' => 'Original Article',
                'content' => 'Original content',
                'status' => 'draft'
            ],
            relationships: [
                'author' => [
                    'data' => ['type' => 'authors-for-special-tags', 'id' => $author->getId()]
                ],
                'specialTags' => [
                    'data' => [
                        ['type' => 'special-tags', 'id' => $phpTag->getId()]
                    ]
                ]
            ]
        );

        $article = $this->validatingProcessor->processCreate('articles-with-special-tags', $createChanges);
        $this->em->flush();
        $articleId = $article->getId();

        // Now update the article's specialTags relationship
        $symfonyTag = $this->em->getRepository(SpecialTag::class)->findOneBy(['name' => 'Symfony']);
        $this->assertNotNull($symfonyTag);

        $updateChanges = new ChangeSet(
            attributes: [],
            relationships: [
                'specialTags' => [ // This uses the alias!
                    'data' => [
                        ['type' => 'special-tags', 'id' => $symfonyTag->getId()]
                    ]
                ]
            ]
        );

        // This should work without throwing NoSuchPropertyException
        $this->validatingProcessor->processUpdate('articles-with-special-tags', $articleId, $updateChanges);
        $this->em->flush();
        $this->em->clear();

        // Verify the relationship was updated correctly
        $reloadedArticle = $this->em->find(ArticleWithSpecialTags::class, $articleId);
        $this->assertNotNull($reloadedArticle);

        $specialTags = $reloadedArticle->getSpecialTags();
        $this->assertCount(1, $specialTags);
        $this->assertSame('Symfony', $specialTags->first()->getName());

        // Verify the relationship was updated correctly
        $specialTags = $reloadedArticle->getSpecialTags();
        $this->assertCount(1, $specialTags);
        $this->assertSame('Symfony', $specialTags->first()->getName());
    }
}
