# Examples & Recipes

**Version**: 0.1.0  
**Last Updated**: 2025-10-07

---

## Table of Contents

1. [Basic CRUD Operations](#basic-crud-operations)
2. [Working with Relationships](#working-with-relationships)
3. [Advanced Queries](#advanced-queries)
4. [Authentication & Authorization](#authentication--authorization)
5. [Validation](#validation)
6. [File Uploads](#file-uploads)
7. [Soft Deletes](#soft-deletes)
8. [Audit Trail](#audit-trail)
9. [Multi-Tenancy](#multi-tenancy)
10. [Real-World Scenarios](#real-world-scenarios)

---

## Basic CRUD Operations

### Create a Resource

```bash
curl -X POST \
     -H "Content-Type: application/vnd.api+json" \
     -H "Accept: application/vnd.api+json" \
     -d '{
       "data": {
         "type": "articles",
         "attributes": {
           "title": "Getting Started with JSON:API",
           "content": "This is a comprehensive guide..."
         }
       }
     }' \
     http://localhost:8000/api/articles
```

**Response (201 Created):**
```json
{
  "jsonapi": { "version": "1.1" },
  "data": {
    "type": "articles",
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "attributes": {
      "title": "Getting Started with JSON:API",
      "content": "This is a comprehensive guide...",
      "createdAt": "2025-10-07T10:00:00+00:00"
    },
    "links": {
      "self": "http://localhost:8000/api/articles/550e8400-e29b-41d4-a716-446655440000"
    }
  }
}
```

### Read a Collection

```bash
curl -H "Accept: application/vnd.api+json" \
     "http://localhost:8000/api/articles?page[number]=1&page[size]=10"
```

### Read a Single Resource

```bash
curl -H "Accept: application/vnd.api+json" \
     http://localhost:8000/api/articles/550e8400-e29b-41d4-a716-446655440000
```

### Update a Resource

```bash
curl -X PATCH \
     -H "Content-Type: application/vnd.api+json" \
     -H "Accept: application/vnd.api+json" \
     -d '{
       "data": {
         "type": "articles",
         "id": "550e8400-e29b-41d4-a716-446655440000",
         "attributes": {
           "title": "Updated Title"
         }
       }
     }' \
     http://localhost:8000/api/articles/550e8400-e29b-41d4-a716-446655440000
```

### Delete a Resource

```bash
curl -X DELETE \
     -H "Accept: application/vnd.api+json" \
     http://localhost:8000/api/articles/550e8400-e29b-41d4-a716-446655440000
```

**Response (204 No Content)**

---

## Working with Relationships

### Create Resource with Relationship

```bash
curl -X POST \
     -H "Content-Type: application/vnd.api+json" \
     -d '{
       "data": {
         "type": "articles",
         "attributes": {
           "title": "My Article"
         },
         "relationships": {
           "author": {
             "data": {
               "type": "authors",
               "id": "author-123"
             }
           }
         }
       }
     }' \
     http://localhost:8000/api/articles
```

### Include Related Resources

```bash
# Include single relationship
curl "http://localhost:8000/api/articles/1?include=author"

# Include multiple relationships
curl "http://localhost:8000/api/articles/1?include=author,tags"

# Include nested relationships
curl "http://localhost:8000/api/articles/1?include=author.articles"
```

**Response:**
```json
{
  "data": {
    "type": "articles",
    "id": "1",
    "attributes": { "title": "My Article" },
    "relationships": {
      "author": {
        "data": { "type": "authors", "id": "123" }
      }
    }
  },
  "included": [
    {
      "type": "authors",
      "id": "123",
      "attributes": { "name": "Alice" }
    }
  ]
}
```

### Fetch Relationship Data

```bash
# Get relationship linkage
curl "http://localhost:8000/api/articles/1/relationships/author"

# Get related resource
curl "http://localhost:8000/api/articles/1/author"
```

### Update Relationships

```bash
# Replace to-one relationship
curl -X PATCH \
     -H "Content-Type: application/vnd.api+json" \
     -d '{
       "data": {
         "type": "authors",
         "id": "new-author-id"
       }
     }' \
     http://localhost:8000/api/articles/1/relationships/author

# Replace to-many relationship
curl -X PATCH \
     -H "Content-Type: application/vnd.api+json" \
     -d '{
       "data": [
         { "type": "tags", "id": "1" },
         { "type": "tags", "id": "2" }
       ]
     }' \
     http://localhost:8000/api/articles/1/relationships/tags

# Add to to-many relationship
curl -X POST \
     -H "Content-Type: application/vnd.api+json" \
     -d '{
       "data": [
         { "type": "tags", "id": "3" }
       ]
     }' \
     http://localhost:8000/api/articles/1/relationships/tags

# Remove from to-many relationship
curl -X DELETE \
     -H "Content-Type: application/vnd.api+json" \
     -d '{
       "data": [
         { "type": "tags", "id": "2" }
       ]
     }' \
     http://localhost:8000/api/articles/1/relationships/tags
```

---

## Advanced Queries

### Sparse Fieldsets

```bash
# Request only specific fields
curl "http://localhost:8000/api/articles?fields[articles]=title,createdAt"

# Sparse fieldsets for multiple types
curl "http://localhost:8000/api/articles?include=author&fields[articles]=title&fields[authors]=name"
```

### Sorting

```bash
# Sort ascending
curl "http://localhost:8000/api/articles?sort=title"

# Sort descending
curl "http://localhost:8000/api/articles?sort=-createdAt"

# Multiple sort fields
curl "http://localhost:8000/api/articles?sort=-createdAt,title"
```

### Pagination

```bash
# Page-based pagination
curl "http://localhost:8000/api/articles?page[number]=2&page[size]=10"

# Navigate using links
curl "http://localhost:8000/api/articles"
# Response includes: links.next, links.prev, links.first, links.last
```

### Filtering (when implemented)

```bash
# Simple filter
curl "http://localhost:8000/api/articles?filter[title][eq]=My Article"

# Multiple filters
curl "http://localhost:8000/api/articles?filter[status][eq]=published&filter[author][eq]=123"

# Range filter
curl "http://localhost:8000/api/articles?filter[createdAt][gte]=2025-01-01"
```

### Complex Query

```bash
curl "http://localhost:8000/api/articles?\
include=author,tags&\
fields[articles]=title,createdAt&\
fields[authors]=name&\
sort=-createdAt&\
page[number]=1&\
page[size]=10"
```

---

## Authentication & Authorization

### JWT Authentication

```php
// src/Security/JwtAuthenticator.php
namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class JwtAuthenticator extends AbstractAuthenticator
{
    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): Passport
    {
        $token = $request->headers->get('Authorization');
        $token = str_replace('Bearer ', '', $token);

        // Validate JWT and extract user identifier
        $userId = $this->validateToken($token);

        return new SelfValidatingPassport(
            new UserBadge($userId)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // Let the request continue
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'errors' => [
                [
                    'status' => '401',
                    'title' => 'Unauthorized',
                    'detail' => 'Invalid or expired token'
                ]
            ]
        ], 401);
    }
}
```

### Resource-Level Authorization

```php
// src/Security/Voter/ArticleVoter.php
namespace App\Security\Voter;

use App\Entity\Article;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class ArticleVoter extends Voter
{
    const EDIT = 'edit';
    const DELETE = 'delete';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE])
            && $subject instanceof Article;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return false;
        }

        /** @var Article $article */
        $article = $subject;

        return match($attribute) {
            self::EDIT => $this->canEdit($article, $user),
            self::DELETE => $this->canDelete($article, $user),
            default => false,
        };
    }

    private function canEdit(Article $article, UserInterface $user): bool
    {
        // Only author can edit
        return $article->getAuthor()->getId() === $user->getId();
    }

    private function canDelete(Article $article, UserInterface $user): bool
    {
        // Only author or admin can delete
        return $article->getAuthor()->getId() === $user->getId()
            || in_array('ROLE_ADMIN', $user->getRoles());
    }
}
```

**Use in persister:**

```php
public function update(string $type, string $id, ChangeSet $changes): object
{
    $article = $this->em->find(Article::class, $id);
    
    if (!$this->security->isGranted('edit', $article)) {
        throw new AccessDeniedException('You cannot edit this article');
    }
    
    // ... update logic
}
```

---

## Validation

### Using Symfony Validator

```php
// src/Entity/Article.php
use Symfony\Component\Validator\Constraints as Assert;

#[JsonApiResource(type: 'articles')]
class Article
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 255)]
    #[Attribute]
    private string $title;

    #[Assert\NotBlank]
    #[Assert\Length(min: 10)]
    #[Attribute]
    private string $content;

    #[Assert\Email]
    #[Attribute]
    private string $authorEmail;
}
```

**Validate in persister:**

```php
use Symfony\Component\Validator\Validator\ValidatorInterface;
use AlexFigures\Symfony\Http\Exception\ValidationException;

class DoctrineArticlePersister implements ResourcePersister
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface $validator,
    ) {}

    public function create(string $type, ChangeSet $changes, ?string $clientId = null): object
    {
        $article = new Article();
        $this->applyAttributes($article, $changes);

        $errors = $this->validator->validate($article);
        
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $this->em->persist($article);
        $this->em->flush();

        return $article;
    }
}
```

---

## File Uploads

### Handle File Upload Attribute

```php
// src/Entity/Article.php
#[JsonApiResource(type: 'articles')]
class Article
{
    #[Attribute]
    private string $title;

    #[Attribute]
    private ?string $coverImageUrl = null;

    // Store file metadata
    private ?string $coverImagePath = null;
}
```

**Process upload in persister:**

```php
public function create(string $type, ChangeSet $changes, ?string $clientId = null): object
{
    $article = new Article();
    
    foreach ($changes->attributes as $key => $value) {
        if ($key === 'coverImage' && is_string($value)) {
            // Assume base64-encoded image
            $imagePath = $this->uploadService->uploadBase64($value);
            $article->setCoverImagePath($imagePath);
            $article->setCoverImageUrl($this->urlGenerator->generate($imagePath));
        } else {
            // Handle other attributes
        }
    }
    
    // ... persist
}
```

**Client request:**

```json
{
  "data": {
    "type": "articles",
    "attributes": {
      "title": "My Article",
      "coverImage": "data:image/png;base64,iVBORw0KGgoAAAANS..."
    }
  }
}
```

---

## Soft Deletes

### Implement Soft Delete Profile

```php
// src/JsonApi/Profile/SoftDeleteProfile.php
namespace App\JsonApi\Profile;

use AlexFigures\Symfony\Profile\Hook\QueryHook;
use AlexFigures\Symfony\Profile\Hook\WriteHook;
use AlexFigures\Symfony\Profile\ProfileInterface;
use AlexFigures\Symfony\Query\Criteria;

class SoftDeleteProfile implements ProfileInterface
{
    public const URI = 'urn:example:soft-delete';

    public function uri(): string
    {
        return self::URI;
    }

    public function hooks(): iterable
    {
        yield new SoftDeleteQueryHook();
        yield new SoftDeleteWriteHook();
    }
}

class SoftDeleteQueryHook implements QueryHook
{
    public function onParseQuery(ProfileContext $context, Request $request, Criteria $criteria): void
    {
        // Add filter to exclude soft-deleted items
        // This integrates with your repository implementation
    }
}

class SoftDeleteWriteHook implements WriteHook
{
    public function onBeforeDelete(ProfileContext $context, string $type, string $id): void
    {
        // Mark as deleted instead of actually deleting
    }
}
```

---

## Audit Trail

See [Advanced Features - Profiles](advanced-features.md#profiles-rfc-6906) for complete audit trail example.

---

## Multi-Tenancy

### Tenant-Scoped Repository

```php
class TenantScopedArticleRepository implements ResourceRepository
{
    public function __construct(
        private EntityManagerInterface $em,
        private TenantContext $tenantContext,
    ) {}

    public function findCollection(string $type, Criteria $criteria): Slice
    {
        $qb = $this->em->createQueryBuilder()
            ->select('a')
            ->from(Article::class, 'a')
            ->where('a.tenantId = :tenantId')
            ->setParameter('tenantId', $this->tenantContext->getCurrentTenantId());

        // ... apply pagination, sorting, etc.
    }
}
```

---

## Real-World Scenarios

### Blog API

Complete blog API with articles, authors, comments, and tags.

See test fixtures for working example:
- `tests/Fixtures/Model/Article.php`
- `tests/Fixtures/Model/Author.php`
- `tests/Fixtures/Model/Tag.php`

### E-Commerce API

Products, categories, orders, customers.

### Social Network API

Users, posts, comments, likes, follows.

---

## See Also

- [Getting Started Guide](getting-started.md)
- [Configuration Reference](configuration.md)
- [Advanced Features](advanced-features.md)
- [Troubleshooting Guide](troubleshooting.md)

---

**Last Updated**: 2025-10-07

