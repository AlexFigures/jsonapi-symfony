# Path Aliases

Path Aliases allow you to expose clean, intuitive API paths that map to complex Doctrine relationship paths. This feature is perfect for scenarios where you have join tables with extra fields or deep relationship hierarchies that you want to simplify for API consumers.

## Overview

Instead of exposing complex internal paths like `articleTags.tag.name`, you can create clean aliases like `tags.name` that automatically resolve to the correct Doctrine path.

**Without Path Aliases:**
```bash
# Complex, internal structure exposed
GET /api/articles?filter[articleTags.tag.name]=PHP&include=articleTags.tag
```

**With Path Aliases:**
```bash
# Clean, intuitive API
GET /api/articles?filter[tags.name]=PHP&include=tags
```

## Basic Usage

Use the `propertyPath` parameter in the `#[Relationship]` attribute to define path aliases:

```php
use AlexFigures\Symfony\Resource\Attribute\JsonApiResource;
use AlexFigures\Symfony\Resource\Attribute\Relationship;
use AlexFigures\Symfony\Resource\Metadata\RelationshipLinkingPolicy;

#[JsonApiResource(type: 'articles')]
class Article
{
    // Regular relationship collection (not exposed as JSON:API resource)
    #[ORM\OneToMany(targetEntity: ArticleTag::class, mappedBy: 'article')]
    private Collection $articleTags;

    // Clean API alias that maps to complex path
    #[Relationship(
        toMany: true,
        targetType: 'tags',
        propertyPath: 'articleTags.tag',  // Maps to: $this->articleTags->map(fn($at) => $at->getTag())
        linkingPolicy: RelationshipLinkingPolicy::VERIFY
    )]
    private Collection $tags;  // Virtual property - computed via propertyPath
}
```

## Join Table Scenarios

Path aliases are particularly useful when working with join tables that have extra fields:

```php
// Join table entity (NOT a JSON:API resource)
#[ORM\Entity]
class ArticleTag
{
    #[ORM\ManyToOne(targetEntity: Article::class)]
    private Article $article;

    #[ORM\ManyToOne(targetEntity: Tag::class)]
    private Tag $tag;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $addedBy = null;  // Extra field

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;  // Extra field
}

// Tag entity (JSON:API resource)
#[JsonApiResource(type: 'tags')]
class Tag
{
    #[Id] #[Attribute] public string $id;
    #[Attribute] public string $name;
    #[Attribute] public string $category;
}
```

With this setup, the API exposes a clean interface:

```bash
# Filter by tag properties
GET /api/articles?filter[tags.name]=PHP
GET /api/articles?filter[tags.category]=language

# Sort by tag properties
GET /api/articles?sort=tags.name

# Include tags in response
GET /api/articles?include=tags
```

## Filtering and Sorting

Path aliases work seamlessly with filtering and sorting. You need to include the alias in your `FilterableFields` and `SortableFields`:

```php
#[JsonApiResource(type: 'articles')]
#[FilterableFields([
    'title',
    new FilterableField('author', inherit: true),
    new FilterableField('tags', inherit: true),  // Enable filtering on aliased relationship
])]
#[SortableFields([
    'title',
    'createdAt',
    new SortableField('author', inherit: true),
    new SortableField('tags', inherit: true),   // Enable sorting on aliased relationship
])]
class Article
{
    // ... relationship definition with propertyPath
}
```

## Deep Path Resolution

Path aliases support multi-level navigation:

```php
#[Relationship(
    targetType: 'countries',
    propertyPath: 'author.address.country'  // Navigate: article → author → address → country
)]
private ?Country $country;
```

This allows clean API queries:
```bash
GET /api/articles?filter[country.code]=US&include=country
```

## Multiple Aliases

You can create multiple aliases pointing to the same or different paths:

```php
class Article
{
    #[Relationship(
        targetType: 'authors',
        propertyPath: 'author'
    )]
    private ?Author $creator;  // Alias: creator → author

    #[Relationship(
        targetType: 'authors',
        propertyPath: 'author'
    )]
    private ?Author $writer;   // Alias: writer → author (same path, different semantic meaning)
}
```

## Implementation Details

### How Path Resolution Works

When you define `propertyPath: 'articleTags.tag'`, the framework:

1. **During Filtering/Sorting**: Resolves the alias to the full Doctrine path before building queries
2. **During Include Processing**: Walks the path segments to collect the target resources
3. **During Serialization**: Uses the PropertyAccessor to navigate the object graph

### Performance Considerations

- **No Additional Queries**: Path aliases don't generate extra database queries
- **Optimized JOINs**: The framework automatically creates efficient JOINs based on the resolved paths
- **Eager Loading**: Included relationships are eagerly loaded to prevent N+1 queries

### Virtual Properties

The property decorated with `#[Relationship(propertyPath: '...')]` is considered "virtual":

```php
class Article
{
    // Real Doctrine property
    #[ORM\OneToMany(targetEntity: ArticleTag::class, mappedBy: 'article')]
    private Collection $articleTags;

    // Virtual property - computed via propertyPath
    #[Relationship(propertyPath: 'articleTags.tag')]
    private Collection $tags;  // This doesn't need to be initialized or managed
}
```

You can optionally implement a getter for the virtual property:

```php
public function getTags(): Collection
{
    return new ArrayCollection(
        $this->articleTags->map(fn(ArticleTag $at) => $at->getTag())->toArray()
    );
}
```

## Best Practices

### 1. Keep Aliases Semantic

Choose alias names that make sense from the API consumer's perspective:

```php
// ✅ Good: Semantic, clear purpose
#[Relationship(targetType: 'tags', propertyPath: 'articleTags.tag')]
private Collection $tags;

#[Relationship(targetType: 'authors', propertyPath: 'author')]
private ?Author $creator;

// ❌ Avoid: Technical, exposes internal structure
#[Relationship(targetType: 'tags', propertyPath: 'articleTags.tag')]
private Collection $articleTagTags;
```

### 2. Document Complex Paths

For complex paths, add clear documentation:

```php
/**
 * Virtual relationship to access the primary category through the article's
 * category assignments. Returns the category marked as 'primary' in the
 * ArticleCategoryAssignment join table.
 */
#[Relationship(
    targetType: 'categories',
    propertyPath: 'categoryAssignments.category'
)]
private ?Category $primaryCategory;
```

### 3. Use Consistent Naming

Maintain consistency across your API:

```php
// ✅ Consistent pattern
class Article
{
    #[Relationship(propertyPath: 'articleTags.tag')] private Collection $tags;
    #[Relationship(propertyPath: 'articleCategories.category')] private Collection $categories;
}

class Product
{
    #[Relationship(propertyPath: 'productTags.tag')] private Collection $tags;
    #[Relationship(propertyPath: 'productCategories.category')] private Collection $categories;
}
```

## Troubleshooting

### Common Issues

**1. "Property path not found" errors**

Ensure the path exists and all intermediate properties have proper getters:

```php
// Make sure this path is valid:
// $article->getArticleTags() → Collection<ArticleTag>
// $articleTag->getTag() → Tag
#[Relationship(propertyPath: 'articleTags.tag')]
```

**2. "Filter field not allowed" errors**

Add the alias to your filterable fields:

```php
#[FilterableFields([
    new FilterableField('tags', inherit: true),  // Don't forget this!
])]
```

**3. Missing relationships in includes**

Verify the target entity is registered as a JSON:API resource:

```php
// Make sure Tag is registered
#[JsonApiResource(type: 'tags')]
class Tag { /* ... */ }
```

### Debug Tips

1. **Check Query Logs**: Enable Doctrine query logging to see the generated SQL
2. **Use Profiler**: The Symfony profiler shows the resolved paths and generated queries
3. **Test Incrementally**: Start with simple paths and gradually add complexity

## Migration Guide

### From Direct Relationships

If you're migrating from direct relationship exposure:

```php
// Before: Exposing join table entity
#[JsonApiResource(type: 'article-tags')]
class ArticleTag
{
    #[Relationship(targetType: 'articles')] private Article $article;
    #[Relationship(targetType: 'tags')] private Tag $tag;
}

// After: Using path aliases
#[JsonApiResource(type: 'articles')]
class Article
{
    #[Relationship(propertyPath: 'articleTags.tag')] private Collection $tags;
}
```

### API Changes

Your API endpoints will change:

```bash
# Before
GET /api/article-tags?filter[tag.name]=PHP&include=article,tag

# After
GET /api/articles?filter[tags.name]=PHP&include=tags
```

Update your API consumers accordingly.

## Related Documentation

- [Relationship Configuration](integration-doctrine.md#relationships)
- [Filtering Guide](filterable-fields.md)
- [Sorting Configuration](sorting-configuration.md)
- [Advanced Features](advanced-features.md)
