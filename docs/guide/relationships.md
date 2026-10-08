# Relationships

Declare a `Relationship` on a mapped association with its target JSON:API type. The resource representation can contain linkage; `/api/{type}/{id}/{relationship}` returns related resource representations, and `/api/{type}/{id}/relationships/{relationship}` returns identifiers.

For example, add a mapped author to the Article entity from quick start:

```php
use AlexFigures\JsonApi\Resource\Attribute\Relationship;
use Doctrine\ORM\Mapping as ORM;

#[ORM\ManyToOne(targetEntity: Author::class)]
#[Relationship(targetType: 'authors')]
public ?Author $author = null;
```

`Author` must be mapped and registered as a resource with type `authors` and one API ID. Read `/api/articles/1/author` for the author representation and `/api/articles/1/relationships/author` for `{ "data": { "type": "authors", "id": "2" } }` linkage. A missing optional to-one target produces `data: null`.

Native Doctrine endpoints apply membership and repository visibility in SQL, then filter, sort and page distinct targets. They do not initialize the entire owner collection to paginate it. `page[number]` and `page[size]` apply to to-many endpoints. Standalone linkage observes `limits.relationship_max_identifiers` as well as page limits.

`relationships.linkage_in_resource` chooses `always`, `when_included` or `never`. `always` is the retained default, bounded by identifier limits. For large collections choose `when_included` or `never`. An overflow is an error, never silently truncated successful linkage.

Custom/computed relationships must implement scoped endpoint pagination themselves. Representation loading is a separate optional batch-reader capability; a typed endpoint reader alone does not make serialization bounded. See [data layer](data-layer-configuration.md), [extension examples](../api/extension-examples.md) and [production policies](production-policies.md).

Writes require `write.allow_relationship_writes: true` and the applicable operation semantics. Applications can bind `RelationshipAuthorizerInterface` for a controlled 403 before provider work. Resource linking defaults apply unless the relationship explicitly overrides them; `VERIFY` and `REFERENCE` are persistence policies, not authorization.

With relationship writes enabled, `PATCH /api/articles/1/relationships/author` accepts a top-level `data` identifier or null. To-many PATCH replaces linkage; POST adds identifiers and DELETE removes identifiers. Send a `data` array for those to-many requests, not resource attributes. Use the current owner's If-Match validator when write preconditions require it. An identifier does not authorize access to its target.

Standalone writes return linkage by default; `relationships.write_response: '204'` selects an empty success response. Computed relationship adapters and declared hook requirements are covered in [scopes and batch loading](relationship-loading.md).
