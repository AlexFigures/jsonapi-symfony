# CRUD and validation

Start with the entity and configuration in [quick start](quick-start.md). Generated operations use the resource type and effective route prefix. With `type: 'articles'` and `/api`, the endpoints are:

| Operation | Request | Successful default response |
| --- | --- | --- |
| INDEX | `GET /api/articles` | 200, collection document |
| SHOW | `GET /api/articles/1` | 200, resource document |
| CREATE | `POST /api/articles` | 201, resource document and Location |
| UPDATE | `PATCH /api/articles/1` | 200, updated resource document |
| DELETE | `DELETE /api/articles/1` | 204, empty body |

Enable only the desired `ResourceOperation` values in `JsonApiResource(operations: [...])`. Generated documentation reflects those choices. A resource without SHOW has no generated item self link. Resource-level `routePrefix` overrides the global prefix; use the resulting links instead of constructing URLs from a global assumption.

## Create

Send `Accept: application/vnd.api+json` and `Content-Type: application/vnd.api+json`:

```json
{"data":{"type":"articles","attributes":{"title":"First article"}}}
```

Let the provider generate the ID unless client-generated IDs are explicitly enabled for that resource. Protocol IDs are strings even when the database uses integers. Declare exposed attributes with `Attribute`; serializer normalization and denormalization groups control reading and writing separately. Symfony serializer YAML/XML metadata is respected alongside attributes.

## Update and delete

Read the item first and retain its ETag. Required write preconditions are enabled by default. Pass the returned validator unchanged, including its quotes:

```bash
curl -X PATCH -H 'Accept: application/vnd.api+json' \
  -H 'Content-Type: application/vnd.api+json' -H 'If-Match: "<etag-from-get>"' \
  --data '{"data":{"type":"articles","id":"1","attributes":{"title":"Updated"}}}' \
  http://localhost:8000/api/articles/1

curl -X DELETE -H 'Accept: application/vnd.api+json' \
  -H 'If-Match: "<current-etag>"' http://localhost:8000/api/articles/1
```

PATCH updates the supplied fields; omitted fields are not replacement instructions. Body identity must agree with the addressed resource. Read again after a successful update before issuing another conditional mutation. Missing required If-Match returns 428; a stale validator returns 412 without mutation. `If-Match: *` tests existence. See [profiles and caching](advanced-features.md) for configuration and [production policies](production-policies.md) for the concurrency boundary.

## Input validation and write models

Use Symfony constraints on writable fields. Invalid input is mapped to JSON:API errors with the applicable attribute/relationship source. Unknown fields, malformed linkage, invalid identities and read-only input are rejected rather than silently persisted. [Errors](../api/errors.md) defines status/code/source guarantees.

For a separate input DTO, configure `JsonApiResource(writeRequests: ['create' => CreateArticleInput::class, 'update' => UpdateArticleInput::class])`. The bundle validates the selected DTO and maps it through the write mapper; serialization groups and validation groups are separate concerns. Configure custom mapping/persistence through the [public extension contracts](../api/public-api.md) and [examples](../api/extension-examples.md).

Relationship mutation is opt-in and described in [relationships](relationships.md). Application permissions and tenant visibility must be enforced by application services; exposing a writable field does not grant authorization.
