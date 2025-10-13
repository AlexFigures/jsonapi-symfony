# Doctrine ORM Integration Guide

**Version**: 0.1.0  
**Last Updated**: 2025-10-07

---

## Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Entity Setup](#entity-setup)
4. [Repository Implementation](#repository-implementation)
5. [Persister Implementation](#persister-implementation)
6. [Relationship Handling](#relationship-handling)
7. [Transaction Management](#transaction-management)
8. [Complete Example](#complete-example)
9. [Best Practices](#best-practices)

---

## Overview

This guide shows how to integrate JsonApiBundle with Doctrine ORM, the most popular ORM for Symfony applications.

**What you'll learn:**
- How to map Doctrine entities to JSON:API resources
- Implementing repositories for read operations
- Implementing persisters for write operations
- Handling relationships efficiently
- Managing transactions

---

## Prerequisites

Install Doctrine ORM if you haven't already:

```bash
composer require symfony/orm-pack
composer require symfony/maker-bundle --dev
```

Configure your database:

```yaml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'
    orm:
        auto_generate_proxy_classes: true
        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
        auto_mapping: true
        mappings:
            App:
                is_bundle: false
                dir: '%kernel.project_dir%/src/Entity'
                prefix: 'App\Entity'
                alias: App
```

---

## Entity Setup

### Step 1: Create Doctrine Entity

Create a Doctrine entity with JSON:API attributes:

```php
// src/Entity/Article.php
namespace App\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use AlexFigures\Symfony\Resource\Attribute\Attribute;
use AlexFigures\Symfony\Resource\Attribute\Id;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use AlexFigures\Symfony\Resource\Attribute\Relationship;

#[ORM\Entity]
#[ORM\Table(name: 'articles')]
#[JsonApiResource(type: 'articles')]
class Article
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Id]
    #[Attribute]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Attribute]
    private string $title;

    #[ORM\Column(type: 'text')]
    #[Attribute]
    private string $content;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Attribute(writable: false)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Attribute(writable: false)]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: Author::class, inversedBy: 'articles')]
    #[ORM\JoinColumn(nullable: false)]
    #[Relationship(targetType: 'authors')]
    private ?Author $author = null;

    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: 'article_tags')]
    #[Relationship(toMany: true, targetType: 'tags')]
    private Collection $tags;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
    }

    // Getters and setters
    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getAuthor(): ?Author
    {
        return $this->author;
    }

    public function setAuthor(?Author $author): self
    {
        $this->author = $author;
        return $this;
    }

    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): self
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }
        return $this;
    }

    public function removeTag(Tag $tag): self
    {
        $this->tags->removeElement($tag);
        return $this;
    }
}
```

### Step 2: Create Related Entities

```php
// src/Entity/Author.php
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use AlexFigures\Symfony\Resource\Attribute\Attribute;
use AlexFigures\Symfony\Resource\Attribute\Id;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use AlexFigures\Symfony\Resource\Attribute\Relationship;

#[ORM\Entity]
#[ORM\Table(name: 'authors')]
#[JsonApiResource(type: 'authors')]
class Author
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Id]
    #[Attribute]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Attribute]
    private string $name;

    #[ORM\Column(type: 'string', length: 255)]
    #[Attribute]
    private string $email;

    #[ORM\OneToMany(targetEntity: Article::class, mappedBy: 'author')]
    #[Relationship(toMany: true, targetType: 'articles', inverse: 'author')]
    private Collection $articles;

    public function __construct()
    {
        $this->articles = new ArrayCollection();
    }

    // Getters and setters...
}
```

```php
// src/Entity/Tag.php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use AlexFigures\Symfony\Resource\Attribute\Attribute;
use AlexFigures\Symfony\Resource\Attribute\Id;
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;

#[ORM\Entity]
#[ORM\Table(name: 'tags')]
#[JsonApiResource(type: 'tags')]
class Tag
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Id]
    #[Attribute]
    private string $id;

    #[ORM\Column(type: 'string', length: 100)]
    #[Attribute]
    private string $name;

    // Getters and setters...
}
```

### Step 3: Generate Migration

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

---

## Repository Implementation

Implement `ResourceRepository` for read operations:

```php
// src/JsonApi/Repository/DoctrineArticleRepository.php
namespace App\JsonApi\Repository;

use App\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use AlexFigures\Symfony\Contract\Data\ResourceRepository;
use AlexFigures\Symfony\Contract\Data\Slice;
use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Query\Sorting;

class DoctrineArticleRepository implements ResourceRepository
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function findCollection(string $type, Criteria $criteria): Slice
    {
        $qb = $this->createQueryBuilder();

        // Apply sorting
        foreach ($criteria->sort as $sort) {
            $this->applySort($qb, $sort);
        }

        // Apply pagination
        $offset = ($criteria->pagination->number - 1) * $criteria->pagination->size;
        $qb->setFirstResult($offset);
        $qb->setMaxResults($criteria->pagination->size);

        // Execute query
        $items = $qb->getQuery()->getResult();
        $total = $this->countTotal($qb);

        return new Slice(
            items: $items,
            pageNumber: $criteria->pagination->number,
            pageSize: $criteria->pagination->size,
            totalItems: $total,
        );
    }

    public function findOne(string $type, string $id, Criteria $criteria): ?object
    {
        return $this->em->find(Article::class, $id);
    }

    public function findRelated(string $type, string $relationship, array $identifiers): iterable
    {
        // Extract IDs from ResourceIdentifier objects
        $ids = array_map(fn($identifier) => $identifier->id, $identifiers);

        // Load related resources based on relationship
        return match ($relationship) {
            'author' => $this->em->getRepository(Author::class)->findBy(['id' => $ids]),
            'tags' => $this->em->getRepository(Tag::class)->findBy(['id' => $ids]),
            default => [],
        };
    }

    private function createQueryBuilder(): QueryBuilder
    {
        return $this->em->createQueryBuilder()
            ->select('a')
            ->from(Article::class, 'a');
    }

    private function applySort(QueryBuilder $qb, Sorting $sort): void
    {
        $direction = $sort->desc ? 'DESC' : 'ASC';
        
        // Map JSON:API field names to entity properties
        $field = match ($sort->field) {
            'createdAt' => 'a.createdAt',
            'updatedAt' => 'a.updatedAt',
            'title' => 'a.title',
            default => 'a.' . $sort->field,
        };

        $qb->addOrderBy($field, $direction);
    }

    private function countTotal(QueryBuilder $qb): int
    {
        $countQb = clone $qb;
        $countQb->select('COUNT(a.id)');
        $countQb->setFirstResult(0);
        $countQb->setMaxResults(null);

        return (int) $countQb->getQuery()->getSingleScalarResult();
    }
}
```

**Register the repository:**

```yaml
# config/services.yaml
services:
    App\JsonApi\Repository\DoctrineArticleRepository:
        tags:
            - { name: 'jsonapi.repository', type: 'articles' }
```

---

## Persister Implementation

Implement `ResourcePersister` for write operations:

```php
// src/JsonApi/Persister/DoctrineArticlePersister.php
namespace App\JsonApi\Persister;

use App\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;
use AlexFigures\Symfony\Contract\Data\ChangeSet;
use AlexFigures\Symfony\Contract\Data\ResourcePersister;
use AlexFigures\Symfony\Http\Exception\ConflictException;
use AlexFigures\Symfony\Http\Exception\NotFoundException;
use Symfony\Component\Uid\Uuid;

class DoctrineArticlePersister implements ResourcePersister
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function create(string $type, ChangeSet $changes, ?string $clientId = null): object
    {
        // Check for ID conflict if client provided ID
        if ($clientId !== null && $this->em->find(Article::class, $clientId)) {
            throw new ConflictException("Article with ID {$clientId} already exists");
        }

        $article = new Article();
        $article->setId($clientId ?? Uuid::v4()->toRfc4122());

        // Apply attributes
        $this->applyAttributes($article, $changes);

        $this->em->persist($article);
        $this->em->flush();

        return $article;
    }

    public function update(string $type, string $id, ChangeSet $changes): object
    {
        $article = $this->em->find(Article::class, $id);
        
        if ($article === null) {
            throw new NotFoundException("Article {$id} not found");
        }

        // Apply attributes
        $this->applyAttributes($article, $changes);
        $article->setUpdatedAt(new \DateTimeImmutable());

        $this->em->flush();

        return $article;
    }

    public function delete(string $type, string $id): void
    {
        $article = $this->em->find(Article::class, $id);
        
        if ($article === null) {
            throw new NotFoundException("Article {$id} not found");
        }

        $this->em->remove($article);
        $this->em->flush();
    }

    private function applyAttributes(Article $article, ChangeSet $changes): void
    {
        foreach ($changes->attributes as $key => $value) {
            match ($key) {
                'title' => $article->setTitle($value),
                'content' => $article->setContent($value),
                default => null, // Ignore unknown attributes
            };
        }
    }
}
```

**Register the persister:**

```yaml
# config/services.yaml
services:
    App\JsonApi\Persister\DoctrineArticlePersister:
        tags:
            - { name: 'jsonapi.persister', type: 'articles' }
```

---

## Relationship Handling

Implement `RelationshipReader` for relationship endpoints:

```php
// src/JsonApi/Relationship/DoctrineRelationshipReader.php
namespace App\JsonApi\Relationship;

use App\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;
use AlexFigures\Symfony\Contract\Data\RelationshipReader;
use AlexFigures\Symfony\Contract\Data\Slice;
use AlexFigures\Symfony\Contract\Data\SliceIds;
use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Query\Pagination;

class DoctrineRelationshipReader implements RelationshipReader
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function getToOneId(string $type, string $id, string $rel): ?string
    {
        $article = $this->em->find(Article::class, $id);
        
        return match ($rel) {
            'author' => $article?->getAuthor()?->getId(),
            default => null,
        };
    }

    public function getToManyIds(string $type, string $id, string $rel, Pagination $pagination): SliceIds
    {
        $article = $this->em->find(Article::class, $id);
        
        if ($article === null) {
            return new SliceIds([], 1, $pagination->size, 0);
        }

        $collection = match ($rel) {
            'tags' => $article->getTags(),
            default => new \Doctrine\Common\Collections\ArrayCollection(),
        };

        $total = $collection->count();
        $offset = ($pagination->number - 1) * $pagination->size;
        $items = $collection->slice($offset, $pagination->size);
        
        $ids = array_map(fn($item) => $item->getId(), $items);

        return new SliceIds(
            ids: $ids,
            pageNumber: $pagination->number,
            pageSize: $pagination->size,
            totalItems: $total,
        );
    }

    public function getRelatedResource(string $type, string $id, string $rel): ?object
    {
        $article = $this->em->find(Article::class, $id);
        
        return match ($rel) {
            'author' => $article?->getAuthor(),
            default => null,
        };
    }

    public function getRelatedCollection(string $type, string $id, string $rel, Criteria $criteria): Slice
    {
        $article = $this->em->find(Article::class, $id);
        
        if ($article === null) {
            return new Slice([], 1, $criteria->pagination->size, 0);
        }

        $collection = match ($rel) {
            'tags' => $article->getTags(),
            default => new \Doctrine\Common\Collections\ArrayCollection(),
        };

        $total = $collection->count();
        $offset = ($criteria->pagination->number - 1) * $criteria->pagination->size;
        $items = $collection->slice($offset, $criteria->pagination->size);

        return new Slice(
            items: $items,
            pageNumber: $criteria->pagination->number,
            pageSize: $criteria->pagination->size,
            totalItems: $total,
        );
    }
}
```

---

## Transaction Management

Implement `TransactionManager` for atomic operations:

```php
// src/JsonApi/Transaction/DoctrineTransactionManager.php
namespace App\JsonApi\Transaction;

use Doctrine\ORM\EntityManagerInterface;
use AlexFigures\Symfony\Contract\Tx\TransactionManager;

class DoctrineTransactionManager implements TransactionManager
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function transactional(callable $callback)
    {
        return $this->em->transactional($callback);
    }
}
```

**Register the transaction manager:**

```yaml
# config/services.yaml
services:
    App\JsonApi\Transaction\DoctrineTransactionManager:
        tags:
            - { name: 'jsonapi.transaction_manager' }
```

---

## Complete Example

See the complete working example in the test fixtures:
- `tests/Fixtures/Model/Article.php`
- `tests/Fixtures/InMemory/InMemoryRepository.php`
- `tests/Fixtures/InMemory/InMemoryPersister.php`

---

## Best Practices

### 1. Use Query Optimization

```php
// Eager load relationships to avoid N+1 queries
$qb->leftJoin('a.author', 'author')->addSelect('author');
$qb->leftJoin('a.tags', 'tags')->addSelect('tags');
```

### 2. Validate Input

```php
use Symfony\Component\Validator\Validator\ValidatorInterface;

public function create(string $type, ChangeSet $changes, ?string $clientId = null): object
{
    $article = new Article();
    // ... set properties
    
    $errors = $this->validator->validate($article);
    if (count($errors) > 0) {
        throw new ValidationException($errors);
    }
    
    // ... persist
}
```

### 3. Handle Soft Deletes

```php
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;

#[ORM\Entity]
class Article
{
    use SoftDeleteableEntity;
    
    // ...
}
```

### 4. Use DTOs for Complex Queries

For complex read operations, consider using DTOs instead of entities to improve performance.

---

## See Also

- [Getting Started Guide](getting-started.md)
- [Configuration Reference](configuration.md)
- [Advanced Features](advanced-features.md)
- [Public API Reference](../api/public-api.md)

---

**Last Updated**: 2025-10-07

