# Atomic Operations with Doctrine ORM

This guide explains how to use JSON:API Atomic Operations Extension with Doctrine ORM in jsonapi-symfony.

## Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [Basic Operations](#basic-operations)
- [Relationships](#relationships)
- [Local IDs (LID)](#local-ids-lid)
- [Error Handling](#error-handling)
- [Best Practices](#best-practices)
- [Advanced Scenarios](#advanced-scenarios)

## Overview

The JSON:API Atomic Operations Extension allows you to execute multiple operations (create, update, delete) in a single HTTP request. All operations are executed within a database transaction - either all succeed or all fail together.

### Key Features

- **Transactional**: All operations execute in a single database transaction
- **Ordered**: Operations are processed in the order they appear
- **Local IDs**: Reference resources created in the same request before they receive permanent IDs
- **Relationships**: Create and update relationships between resources
- **Error Handling**: Automatic rollback on any error with detailed error responses

### Endpoint

```
POST /jsonapi/atomic
Content-Type: application/vnd.api+json; ext="https://jsonapi.org/ext/atomic"
```

## Architecture

### Components

1. **AtomicController** - HTTP entry point, parses request and returns response
2. **OperationDispatcher** - Orchestrates operation execution within transaction
3. **Operation Handlers** - Execute individual operations (AddHandler, UpdateHandler, RemoveHandler)
4. **DoctrineTransactionManager** - Manages database transactions using `EntityManager::wrapInTransaction()`
5. **FlushManager** - Controls when Doctrine flushes changes to database
6. **LidRegistry** - Tracks Local ID to actual ID mappings

### Transaction Flow

```
Request → AtomicController
    ↓
AtomicRequestParser (validates request)
    ↓
DoctrineTransactionManager::transactional()
    ↓
OperationDispatcher::run()
    ↓
For each operation:
    - Execute handler (AddHandler/UpdateHandler/RemoveHandler)
    - FlushManager::flush() (makes entities available for next operation)
    - Register LID if present
    ↓
If any error → Doctrine automatic rollback
If all succeed → Doctrine automatic commit
    ↓
ResultBuilder (builds JSON:API response)
```

## Basic Operations

### Add Operation (Create Resource)

Create a new resource:

```json
{
  "atomic:operations": [
    {
      "op": "add",
      "ref": {
        "type": "articles"
      },
      "data": {
        "type": "articles",
        "attributes": {
          "title": "JSON:API Atomic Operations",
          "content": "A comprehensive guide..."
        }
      }
    }
  ]
}
```

**Response (200 OK):**

```json
{
  "atomic:results": [
    {
      "data": {
        "type": "articles",
        "id": "550e8400-e29b-41d4-a716-446655440000",
        "attributes": {
          "title": "JSON:API Atomic Operations",
          "content": "A comprehensive guide..."
        }
      }
    }
  ]
}
```

### Update Operation

Update an existing resource:

```json
{
  "atomic:operations": [
    {
      "op": "update",
      "ref": {
        "type": "articles",
        "id": "550e8400-e29b-41d4-a716-446655440000"
      },
      "data": {
        "type": "articles",
        "id": "550e8400-e29b-41d4-a716-446655440000",
        "attributes": {
          "title": "Updated Title"
        }
      }
    }
  ]
}
```

**Note**: Only attributes provided in the request are updated. Other attributes remain unchanged.

### Remove Operation

Delete a resource:

```json
{
  "atomic:operations": [
    {
      "op": "remove",
      "ref": {
        "type": "articles",
        "id": "550e8400-e29b-41d4-a716-446655440000"
      }
    }
  ]
}
```

**Response**: 204 No Content (for remove-only operations) or 200 OK with results.

### Multiple Operations

Execute multiple operations in a single request:

```json
{
  "atomic:operations": [
    {
      "op": "add",
      "ref": { "type": "authors" },
      "data": {
        "type": "authors",
        "attributes": {
          "name": "John Doe",
          "email": "john@example.com"
        }
      }
    },
    {
      "op": "add",
      "ref": { "type": "articles" },
      "data": {
        "type": "articles",
        "attributes": {
          "title": "First Article",
          "content": "Content..."
        }
      }
    },
    {
      "op": "update",
      "ref": { "type": "authors", "id": "existing-id" },
      "data": {
        "type": "authors",
        "id": "existing-id",
        "attributes": {
          "name": "Jane Doe"
        }
      }
    }
  ]
}
```

All three operations execute in a single transaction. If any fails, all changes are rolled back.

## Relationships

### Create Resource with To-One Relationship

```json
{
  "atomic:operations": [
    {
      "op": "add",
      "ref": { "type": "articles" },
      "data": {
        "type": "articles",
        "attributes": {
          "title": "Article Title",
          "content": "Content..."
        },
        "relationships": {
          "author": {
            "data": {
              "type": "authors",
              "id": "550e8400-e29b-41d4-a716-446655440000"
            }
          }
        }
      }
    }
  ]
}
```

### Create Resource with To-Many Relationship

```json
{
  "atomic:operations": [
    {
      "op": "add",
      "ref": { "type": "articles" },
      "data": {
        "type": "articles",
        "attributes": {
          "title": "Article Title",
          "content": "Content..."
        },
        "relationships": {
          "tags": {
            "data": [
              { "type": "tags", "id": "tag-1" },
              { "type": "tags", "id": "tag-2" },
              { "type": "tags", "id": "tag-3" }
            ]
          }
        }
      }
    }
  ]
}
```

### Update Relationship

Change an article's author:

```json
{
  "atomic:operations": [
    {
      "op": "update",
      "ref": { "type": "articles", "id": "article-id" },
      "data": {
        "type": "articles",
        "id": "article-id",
        "attributes": {},
        "relationships": {
          "author": {
            "data": {
              "type": "authors",
              "id": "new-author-id"
            }
          }
        }
      }
    }
  ]
}
```

**Note**: Empty `attributes: {}` is required when updating only relationships.

### Clear To-One Relationship

Set relationship to null:

```json
{
  "atomic:operations": [
    {
      "op": "update",
      "ref": { "type": "articles", "id": "article-id" },
      "data": {
        "type": "articles",
        "id": "article-id",
        "attributes": {},
        "relationships": {
          "author": {
            "data": null
          }
        }
      }
    }
  ]
}
```

### Clear To-Many Relationship

Set relationship to empty array:

```json
{
  "atomic:operations": [
    {
      "op": "update",
      "ref": { "type": "articles", "id": "article-id" },
      "data": {
        "type": "articles",
        "id": "article-id",
        "attributes": {},
        "relationships": {
          "tags": {
            "data": []
          }
        }
      }
    }
  ]
}
```

**Important**: To-many relationships cannot be set to `null`. Use empty array `[]` instead.

## Local IDs (LID)

Local IDs allow you to reference resources created in the same atomic request before they receive permanent IDs from the database.

### Basic LID Usage

Create an author and an article that references it:

```json
{
  "atomic:operations": [
    {
      "op": "add",
      "ref": { "type": "authors" },
      "data": {
        "type": "authors",
        "lid": "temp-author-1",
        "attributes": {
          "name": "John Doe",
          "email": "john@example.com"
        }
      }
    },
    {
      "op": "add",
      "ref": { "type": "articles" },
      "data": {
        "type": "articles",
        "attributes": {
          "title": "Article by John",
          "content": "Content..."
        },
        "relationships": {
          "author": {
            "data": {
              "type": "authors",
              "lid": "temp-author-1"
            }
          }
        }
      }
    }
  ]
}
```

**How it works**:
1. Operation 1 creates author with `lid: "temp-author-1"`
2. After operation 1, `FlushManager` flushes to database, author gets real ID
3. `LidRegistry` maps `"temp-author-1"` → actual author ID
4. Operation 2 references `lid: "temp-author-1"` in relationship
5. Handler resolves LID to actual ID before creating article

### Complex LID Scenario

Create multiple resources with cross-references:

```json
{
  "atomic:operations": [
    {
      "op": "add",
      "ref": { "type": "authors" },
      "data": {
        "type": "authors",
        "lid": "author-1",
        "attributes": {
          "name": "Author One",
          "email": "author1@example.com"
        }
      }
    },
    {
      "op": "add",
      "ref": { "type": "tags" },
      "data": {
        "type": "tags",
        "lid": "tag-1",
        "attributes": { "name": "PHP" }
      }
    },
    {
      "op": "add",
      "ref": { "type": "tags" },
      "data": {
        "type": "tags",
        "lid": "tag-2",
        "attributes": { "name": "Doctrine" }
      }
    },
    {
      "op": "add",
      "ref": { "type": "articles" },
      "data": {
        "type": "articles",
        "lid": "article-1",
        "attributes": {
          "title": "Doctrine Guide",
          "content": "Content..."
        },
        "relationships": {
          "author": {
            "data": { "type": "authors", "lid": "author-1" }
          },
          "tags": {
            "data": [
              { "type": "tags", "lid": "tag-1" },
              { "type": "tags", "lid": "tag-2" }
            ]
          }
        }
      }
    },
    {
      "op": "update",
      "ref": { "type": "articles", "lid": "article-1" },
      "data": {
        "type": "articles",
        "lid": "article-1",
        "attributes": {
          "title": "Updated: Doctrine Guide"
        }
      }
    }
  ]
}
```

**Note**: You can reference a LID in subsequent operations, including update operations.

### LID Rules

1. **Scope**: LIDs are only valid within a single atomic request
2. **Uniqueness**: Each LID must be unique within the request
3. **Forward references only**: You can only reference LIDs from previous operations
4. **Automatic resolution**: Handlers automatically resolve LIDs to actual IDs
5. **Flush requirement**: `FlushManager` must flush after each operation for LID resolution to work

## Error Handling

### Automatic Rollback

If any operation fails, all changes are automatically rolled back:

```json
{
  "atomic:operations": [
    {
      "op": "add",
      "ref": { "type": "authors" },
      "data": {
        "type": "authors",
        "attributes": {
          "name": "John Doe",
          "email": "john@example.com"
        }
      }
    },
    {
      "op": "update",
      "ref": { "type": "articles", "id": "non-existent-id" },
      "data": {
        "type": "articles",
        "id": "non-existent-id",
        "attributes": { "title": "Updated" }
      }
    }
  ]
}
```

**Response (404 Not Found):**

```json
{
  "errors": [
    {
      "status": "404",
      "title": "Not Found",
      "detail": "Resource of type \"articles\" with id \"non-existent-id\" was not found.",
      "source": {
        "pointer": "/atomic:operations/1"
      }
    }
  ]
}
```

**Result**: The author created in operation 1 is **NOT** saved to the database. The entire transaction is rolled back.

### Common Errors

#### 1. Resource Not Found

```json
{
  "errors": [
    {
      "status": "404",
      "title": "Not Found",
      "detail": "Resource of type \"articles\" with id \"invalid-id\" was not found."
    }
  ]
}
```

#### 2. Unknown LID

```json
{
  "errors": [
    {
      "status": "400",
      "title": "Bad Request",
      "detail": "Unknown local identifier \"unknown-lid\"."
    }
  ]
}
```

#### 3. Validation Error

```json
{
  "errors": [
    {
      "status": "422",
      "title": "Validation Failed",
      "detail": "This value should not be blank.",
      "source": {
        "pointer": "/data/attributes/title"
      }
    }
  ]
}
```

#### 4. Relationship Not Found

```json
{
  "errors": [
    {
      "status": "404",
      "title": "Not Found",
      "detail": "Related resource of type \"authors\" with id \"invalid-id\" was not found.",
      "source": {
        "pointer": "/data/relationships/author/data"
      }
    }
  ]
}
```

### Error Handling Flow

```
Operation N fails
    ↓
Exception thrown
    ↓
Doctrine catches exception in wrapInTransaction()
    ↓
Doctrine automatically rolls back transaction
    ↓
EntityManager is closed (expected Doctrine behavior)
    ↓
JsonApiExceptionListener catches exception
    ↓
Returns JSON:API error response
```

**Important**: After a failed atomic request, the EntityManager is closed. This is normal Doctrine behavior for exceptions during transactions.

## Best Practices

### 1. Order Operations Correctly

Operations are executed in order. Place create operations before operations that reference them:

**✅ Good:**
```json
{
  "atomic:operations": [
    { "op": "add", "data": { "type": "authors", "lid": "author-1", ... } },
    { "op": "add", "data": { "type": "articles", "relationships": { "author": { "data": { "lid": "author-1" } } } } }
  ]
}
```

**❌ Bad:**
```json
{
  "atomic:operations": [
    { "op": "add", "data": { "type": "articles", "relationships": { "author": { "data": { "lid": "author-1" } } } } },
    { "op": "add", "data": { "type": "authors", "lid": "author-1", ... } }
  ]
}
```

### 2. Use Meaningful LIDs

Use descriptive LID names for better readability:

**✅ Good:**
```json
"lid": "new-author-john-doe"
"lid": "article-doctrine-guide"
"lid": "tag-php"
```

**❌ Bad:**
```json
"lid": "1"
"lid": "temp"
"lid": "x"
```

### 3. Batch Related Operations

Group related operations in a single atomic request to ensure consistency:

```json
{
  "atomic:operations": [
    { "op": "add", "data": { "type": "orders", "lid": "order-1", ... } },
    { "op": "add", "data": { "type": "order-items", "relationships": { "order": { "lid": "order-1" } }, ... } },
    { "op": "add", "data": { "type": "order-items", "relationships": { "order": { "lid": "order-1" } }, ... } },
    { "op": "update", "data": { "type": "inventory", "id": "product-1", "attributes": { "stock": 95 } } }
  ]
}
```

All operations succeed or fail together, ensuring data consistency.

### 4. Handle Bidirectional Relationships

When working with bidirectional relationships (e.g., Author ↔ Article), the library automatically synchronizes both sides:

```json
{
  "atomic:operations": [
    {
      "op": "update",
      "ref": { "type": "articles", "id": "article-1" },
      "data": {
        "type": "articles",
        "id": "article-1",
        "attributes": {},
        "relationships": {
          "author": {
            "data": { "type": "authors", "id": "author-2" }
          }
        }
      }
    }
  ]
}
```

**Result**:
- Article's author is updated to author-2
- Author-2's articles collection includes article-1
- Previous author's articles collection no longer includes article-1

### 5. Limit Batch Size

While there's no hard limit, consider splitting very large batches (>100 operations) into multiple requests:

- Reduces memory usage
- Faster error detection
- Easier debugging
- Better user experience (faster response times)

### 6. Validate Before Sending

Validate your request structure before sending to avoid unnecessary rollbacks:

- Check all required attributes are present
- Verify relationship references exist
- Ensure LIDs are defined before use
- Validate data types match schema

### 7. Use Empty Attributes for Relationship-Only Updates

When updating only relationships, include empty `attributes: {}`:

```json
{
  "op": "update",
  "ref": { "type": "articles", "id": "article-1" },
  "data": {
    "type": "articles",
    "id": "article-1",
    "attributes": {},
    "relationships": {
      "author": { "data": { "type": "authors", "id": "new-author" } }
    }
  }
}
```

## Advanced Scenarios

### Cascading Creates

Create a complete object graph in one request:

```json
{
  "atomic:operations": [
    {
      "op": "add",
      "ref": { "type": "authors" },
      "data": {
        "type": "authors",
        "lid": "author-1",
        "attributes": {
          "name": "John Doe",
          "email": "john@example.com"
        }
      }
    },
    {
      "op": "add",
      "ref": { "type": "tags" },
      "data": {
        "type": "tags",
        "lid": "tag-php",
        "attributes": { "name": "PHP" }
      }
    },
    {
      "op": "add",
      "ref": { "type": "tags" },
      "data": {
        "type": "tags",
        "lid": "tag-doctrine",
        "attributes": { "name": "Doctrine" }
      }
    },
    {
      "op": "add",
      "ref": { "type": "articles" },
      "data": {
        "type": "articles",
        "lid": "article-1",
        "attributes": {
          "title": "Doctrine ORM Guide",
          "content": "Comprehensive guide to Doctrine ORM..."
        },
        "relationships": {
          "author": {
            "data": { "type": "authors", "lid": "author-1" }
          },
          "tags": {
            "data": [
              { "type": "tags", "lid": "tag-php" },
              { "type": "tags", "lid": "tag-doctrine" }
            ]
          }
        }
      }
    },
    {
      "op": "add",
      "ref": { "type": "articles" },
      "data": {
        "type": "articles",
        "attributes": {
          "title": "Advanced Doctrine",
          "content": "Advanced topics..."
        },
        "relationships": {
          "author": {
            "data": { "type": "authors", "lid": "author-1" }
          },
          "tags": {
            "data": [
              { "type": "tags", "lid": "tag-doctrine" }
            ]
          }
        }
      }
    }
  ]
}
```

**Result**: Creates 1 author, 2 tags, and 2 articles with all relationships properly linked.

### Update Then Remove Pattern

Update a resource and then remove it (useful for audit trails):

```json
{
  "atomic:operations": [
    {
      "op": "update",
      "ref": { "type": "articles", "id": "article-1" },
      "data": {
        "type": "articles",
        "id": "article-1",
        "attributes": {
          "status": "archived",
          "archivedAt": "2024-01-15T10:30:00Z"
        }
      }
    },
    {
      "op": "remove",
      "ref": { "type": "articles", "id": "article-1" }
    }
  ]
}
```

**Note**: This pattern is useful when you need to trigger update hooks/events before deletion.

### Bulk Relationship Updates

Update relationships for multiple resources:

```json
{
  "atomic:operations": [
    {
      "op": "update",
      "ref": { "type": "articles", "id": "article-1" },
      "data": {
        "type": "articles",
        "id": "article-1",
        "attributes": {},
        "relationships": {
          "author": { "data": { "type": "authors", "id": "new-author" } }
        }
      }
    },
    {
      "op": "update",
      "ref": { "type": "articles", "id": "article-2" },
      "data": {
        "type": "articles",
        "id": "article-2",
        "attributes": {},
        "relationships": {
          "author": { "data": { "type": "authors", "id": "new-author" } }
        }
      }
    },
    {
      "op": "update",
      "ref": { "type": "articles", "id": "article-3" },
      "data": {
        "type": "articles",
        "id": "article-3",
        "attributes": {},
        "relationships": {
          "author": { "data": { "type": "authors", "id": "new-author" } }
        }
      }
    }
  ]
}
```

**Result**: All three articles now have the same author. If any update fails, none are applied.

### Mixed Operations with LIDs

Combine creates, updates, and deletes with LID references:

```json
{
  "atomic:operations": [
    {
      "op": "add",
      "ref": { "type": "categories" },
      "data": {
        "type": "categories",
        "lid": "new-category",
        "attributes": { "name": "Technology" }
      }
    },
    {
      "op": "update",
      "ref": { "type": "articles", "id": "article-1" },
      "data": {
        "type": "articles",
        "id": "article-1",
        "attributes": {},
        "relationships": {
          "category": {
            "data": { "type": "categories", "lid": "new-category" }
          }
        }
      }
    },
    {
      "op": "remove",
      "ref": { "type": "categories", "id": "old-category-id" }
    }
  ]
}
```

**Result**: Creates new category, updates article to use it, removes old category - all atomically.

## Performance Considerations

### Flush After Each Operation

The library calls `FlushManager::flush()` after each operation to make entities available for subsequent operations (required for LID resolution). This means:

- **N operations = N flushes** (not N+1, final flush is handled by WriteListener)
- Each flush triggers Doctrine's Unit of Work calculations
- For large batches (50+ operations), this may impact performance

**Optimization**: If you don't need LIDs, consider splitting into multiple smaller atomic requests.

### Database Transactions

All operations execute within a single database transaction:

- **Isolation**: Changes are not visible to other connections until commit
- **Locks**: Database may hold locks for the duration of the transaction
- **Timeout**: Very long transactions may hit database timeout limits

**Recommendation**: Keep atomic requests focused and reasonably sized (< 50 operations).

## Troubleshooting

### EntityManager Closed After Error

**Symptom**: After a failed atomic request, subsequent requests fail with "EntityManager is closed".

**Cause**: Doctrine closes the EntityManager when an exception occurs during a transaction.

**Solution**: This is expected behavior. The library handles this correctly - each request gets a fresh EntityManager.

### LID Not Found

**Symptom**: Error "Unknown local identifier \"my-lid\"".

**Causes**:
1. LID referenced before it's defined (wrong operation order)
2. Typo in LID name
3. LID defined in a different atomic request (LIDs don't persist across requests)

**Solution**: Ensure LID is defined in an earlier operation in the same request.

### Relationship Not Applied

**Symptom**: Relationship is not set after atomic operation.

**Causes**:
1. Missing `attributes: {}` in update operation
2. Relationship data format incorrect
3. Related resource doesn't exist

**Solution**:
- Always include `attributes: {}` when updating only relationships
- Verify relationship data format matches JSON:API spec
- Ensure related resources exist or are created earlier in the request

### Validation Errors

**Symptom**: 422 Unprocessable Entity with validation errors.

**Cause**: Entity validation failed (Symfony Validator constraints).

**Solution**:
- Check validation constraints on your entities
- Ensure all required fields are provided
- Verify data types match entity property types

## See Also

- [JSON:API Atomic Operations Extension Specification](https://jsonapi.org/ext/atomic/)
- [Doctrine ORM Transactions](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/transactions-and-concurrency.html)
- [JSON:API Specification](https://jsonapi.org/format/)
- [Symfony Validation](https://symfony.com/doc/current/validation.html)


