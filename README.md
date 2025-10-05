# JsonApiBundle (Stage 2)

A DX-first Symfony 7 bundle scaffold for building fully compliant JSON:API 1.1 backends.

## Installation

```bash
composer require jsonapi/symfony-jsonapi-bundle
```

For local development against this repository:

```bash
git clone https://github.com/your-org/jsonapi-symfony.git
cd jsonapi-symfony
composer install
```

## Usage

Register the bundle in your Symfony application's `config/bundles.php`:

```php
return [
    JsonApi\Symfony\Bridge\Symfony\Bundle\JsonApiBundle::class => ['all' => true],
];
```

Configure the bundle (defaults shown):

```yaml
# config/packages/jsonapi.yaml
jsonapi:
    strict_content_negotiation: true
    media_type: 'application/vnd.api+json'
    route_prefix: '/api'
    pagination:
        default_size: 25
        max_size: 100
    sorting:
        whitelist:
            articles: ['title', 'createdAt']
            authors: ['name']
    write:
        allow_relationship_writes: false
        client_generated_ids:
            articles: false
            authors: true
```

Declare your first resource:

```php
use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
use JsonApi\Symfony\Resource\Attribute\Id;
use JsonApi\Symfony\Resource\Attribute\Attribute;
use JsonApi\Symfony\Resource\Attribute\Relationship;

#[JsonApiResource(type: 'articles')]
final class Article
{
    #[Id]
    public string $id;

    #[Attribute]
    public string $title;

    #[Relationship(toMany: true)]
    public array $comments = [];
}
```

Stage 2 ships fully functional read endpoints and foundational writes:

* Attribute-driven metadata registry with automatic discovery of attributes and relationships.
* `GET /api/{type}` and `GET /api/{type}/{id}` controllers with JSON:API 1.1 compliant documents.
* Query parsing for `include`, `fields[TYPE]`, `sort`, `page[number]`, and `page[size]` with robust validation.
* Pagination helpers generating `self`, `first`, `prev`, `next`, and `last` links that retain other query parameters.
* Document builder producing `data`, `included`, `links`, `meta`, and `jsonapi.version` for any combination of sparse fieldsets and includes.
* In-memory repository and sample fixtures (Article, Author, Tag) for functional testing.
* `POST /api/{type}`, `PATCH /api/{type}/{id}`, and `DELETE /api/{type}/{id}` controllers with ChangeSet-based write ports, transactional execution, and client-generated ID support.

## Writes

Stage 2 adds denormalisation of JSON:API resource documents into a `ChangeSet` consumed by the `ResourcePersister` port. Controllers wrap each write in a `TransactionManager::transactional()` call to guarantee atomicity and return the appropriate JSON:API responses:

* `POST /api/{type}` ⇒ `201 Created` with the newly created resource document and a `Location` header pointing at its `self` link.
* `PATCH /api/{type}/{id}` ⇒ `200 OK` with the updated resource document.
* `DELETE /api/{type}/{id}` ⇒ `204 No Content`.

Input documents are validated strictly:

* `data.type` and `data.id` must match the endpoint.
* Only attributes declared with `#[Attribute(writable: true)]` are accepted; attempts to write read-only or unknown attributes result in `400 Bad Request`.
* Relationship writes are rejected by default (`allow_relationship_writes: false`).
* Client-generated IDs are controlled per type through `write.client_generated_ids`. When disabled the bundle throws `403 Forbidden`; when enabled conflicts surface as `409 Conflict`.

An in-memory `ResourcePersister` backs the functional test suite, using UUIDv4 server-generated identifiers when the client does not supply an ID.

Example response:

```json
{
  "jsonapi": { "version": "1.1" },
  "links": {
    "self": "https://api.example.test/api/articles?page[number]=2&page[size]=5",
    "first": "https://api.example.test/api/articles?page[number]=1&page[size]=5",
    "prev": "https://api.example.test/api/articles?page[number]=1&page[size]=5",
    "next": "https://api.example.test/api/articles?page[number]=3&page[size]=5",
    "last": "https://api.example.test/api/articles?page[number]=3&page[size]=5"
  },
  "data": [
    {
      "type": "articles",
      "id": "42",
      "attributes": {
        "title": "Hello JSON:API",
        "createdAt": "2024-06-01T12:00:00+00:00"
      },
      "links": {
        "self": "https://api.example.test/api/articles/42"
      }
    }
  ],
  "included": [
    {
      "type": "authors",
      "id": "7",
      "attributes": { "name": "Alice" },
      "links": {
        "self": "https://api.example.test/api/authors/7"
      }
    }
  ],
  "meta": {
    "total": 123,
    "page": 2,
    "size": 5
  }
}
```

Upcoming stages will introduce relationship writes, Doctrine persistence adapters, and full JSON:API error documents.
