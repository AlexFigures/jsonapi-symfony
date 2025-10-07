<?php

declare(strict_types=1);

namespace JsonApi\Symfony\StressApp;

use DateInterval;
use DateTimeImmutable;
use JsonApi\Symfony\Contract\Data\ResourceIdentifier;
use JsonApi\Symfony\Contract\Data\ResourceRepository;
use JsonApi\Symfony\Contract\Data\Slice;
use JsonApi\Symfony\Query\Criteria;
use JsonApi\Symfony\Tests\Fixtures\Model\Article;
use JsonApi\Symfony\Tests\Fixtures\Model\Author;
use JsonApi\Symfony\Tests\Fixtures\Model\Tag;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * In-memory repository with large dataset for stress testing.
 *
 * Generates:
 * - 1000 Articles
 * - 100 Authors
 * - 500 Tags
 */
final class StressInMemoryRepository implements ResourceRepository
{
    private const ARTICLES_COUNT = 1000;
    private const AUTHORS_COUNT = 100;
    private const TAGS_COUNT = 500;

    private PropertyAccessorInterface $accessor;

    /**
     * @var array<string, array<int, object>>
     */
    private array $data = [];

    public function __construct(
        private readonly ResourceRegistryInterface $registry,
        ?PropertyAccessorInterface $accessor = null,
    ) {
        $this->accessor = $accessor ?? PropertyAccess::createPropertyAccessor();
        $this->seedLargeDataset();
    }

    public function has(string $type, string $id): bool
    {
        return $this->findModel($type, $id) !== null;
    }

    public function get(string $type, string $id): ?object
    {
        return $this->findModel($type, $id);
    }

    public function add(object $model): void
    {
        $metadata = $this->registry->getByClass($model::class);
        $type = $metadata->type;

        if (!isset($this->data[$type])) {
            $this->data[$type] = [];
        }

        $this->data[$type][] = $model;
    }

    public function remove(object $model): void
    {
        $metadata = $this->registry->getByClass($model::class);
        $type = $metadata->type;

        if (!isset($this->data[$type])) {
            return;
        }

        $idPath = $metadata->idPropertyPath ?? 'id';
        $id = (string) $this->accessor->getValue($model, $idPath);

        foreach ($this->data[$type] as $key => $existing) {
            $existingId = (string) $this->accessor->getValue($existing, $idPath);
            if ($existingId === $id) {
                unset($this->data[$type][$key]);
                return;
            }
        }
    }

    public function findCollection(string $type, Criteria $criteria): Slice
    {
        $items = array_values($this->data[$type] ?? []);

        $total = count($items);
        $size = $criteria->pagination->size;
        $number = $criteria->pagination->number;
        $offset = max(0, ($number - 1) * $size);

        $items = array_slice($items, $offset, $size);

        return new Slice($items, $number, $size, $total);
    }

    public function findOne(string $type, string $id, Criteria $criteria): ?object
    {
        return $this->findModel($type, $id);
    }

    /**
     * @param list<ResourceIdentifier> $identifiers
     * @return iterable<object>
     */
    public function findRelated(string $type, string $relationship, array $identifiers): iterable
    {
        $results = [];

        foreach ($identifiers as $identifier) {
            $model = $this->findModel($identifier->type, $identifier->id);
            if ($model !== null) {
                $results[] = $model;
            }
        }

        return $results;
    }

    private function findModel(string $type, string $id): ?object
    {
        if (!isset($this->data[$type])) {
            return null;
        }

        $metadata = $this->registry->getByType($type);
        $path = $metadata->idPropertyPath ?? 'id';

        foreach ($this->data[$type] as $model) {
            $value = $this->accessor->getValue($model, $path);
            $stringValue = (string) $value;
            if ($stringValue === $id) {
                return $model;
            }
        }

        return null;
    }

    /**
     * Generate large dataset for stress testing
     */
    private function seedLargeDataset(): void
    {
        // Generate Authors
        $authors = [];
        for ($i = 1; $i <= self::AUTHORS_COUNT; $i++) {
            $authors[] = new Author(
                (string) $i,
                $this->generateAuthorName($i)
            );
        }

        // Generate Tags
        $tags = [];
        for ($i = 1; $i <= self::TAGS_COUNT; $i++) {
            $tags[] = new Tag(
                (string) $i,
                $this->generateTagName($i)
            );
        }

        // Generate Articles
        $articles = [];
        $date = new DateTimeImmutable('2024-01-01T10:00:00Z');
        
        for ($i = 1; $i <= self::ARTICLES_COUNT; $i++) {
            // Distribute articles across authors
            $author = $authors[($i - 1) % count($authors)];
            
            // Each article gets 2-5 random tags
            $tagCount = 2 + ($i % 4);
            $articleTags = [];
            for ($j = 0; $j < $tagCount; $j++) {
                $tagIndex = ($i * 7 + $j * 13) % count($tags);
                $articleTags[] = $tags[$tagIndex];
            }
            
            $articles[] = new Article(
                (string) $i,
                $this->generateArticleTitle($i),
                $date,
                $author,
                ...$articleTags
            );
            
            // Increment date by 1 hour for each article
            $date = $date->add(new DateInterval('PT1H'));
        }

        $this->data = [
            'articles' => $articles,
            'authors' => $authors,
            'tags' => $tags,
        ];
    }

    private function generateAuthorName(int $index): string
    {
        $firstNames = ['Alice', 'Bob', 'Carol', 'David', 'Eve', 'Frank', 'Grace', 'Henry', 'Ivy', 'Jack'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'];
        
        $firstName = $firstNames[$index % count($firstNames)];
        $lastName = $lastNames[($index * 3) % count($lastNames)];
        
        return sprintf('%s %s %d', $firstName, $lastName, $index);
    }

    private function generateTagName(int $index): string
    {
        $categories = ['php', 'symfony', 'jsonapi', 'rest', 'api', 'web', 'backend', 'frontend', 'database', 'testing'];
        $modifiers = ['advanced', 'beginner', 'intermediate', 'expert', 'tutorial', 'guide', 'tips', 'tricks', 'best-practices', 'patterns'];
        
        $category = $categories[$index % count($categories)];
        $modifier = $modifiers[($index * 7) % count($modifiers)];
        
        return sprintf('%s-%s-%d', $category, $modifier, $index);
    }

    private function generateArticleTitle(int $index): string
    {
        $templates = [
            'Understanding %s in Modern Development',
            'A Deep Dive into %s',
            'Best Practices for %s',
            'Getting Started with %s',
            'Advanced %s Techniques',
            'The Complete Guide to %s',
            'Mastering %s: Tips and Tricks',
            '%s for Beginners',
            'Optimizing %s Performance',
            'Common %s Pitfalls to Avoid',
        ];
        
        $topics = [
            'API Design', 'REST Architecture', 'JSON:API', 'Symfony Framework',
            'PHP Development', 'Database Optimization', 'Caching Strategies',
            'Testing Methodologies', 'Code Quality', 'Performance Tuning',
            'Security Best Practices', 'Microservices', 'Event-Driven Architecture',
            'Domain-Driven Design', 'SOLID Principles', 'Design Patterns',
            'Continuous Integration', 'DevOps Practices', 'Cloud Deployment',
            'Monitoring and Logging',
        ];
        
        $template = $templates[$index % count($templates)];
        $topic = $topics[($index * 3) % count($topics)];
        
        return sprintf($template . ' #%d', $topic, $index);
    }
}

