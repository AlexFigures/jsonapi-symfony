# Configuration during stabilization

Configure the bundle under `jsonapi:` in `config/packages/jsonapi.yaml`. This page describes current source defaults; it is not a declaration that the 1.0 schema is frozen. The [source schema](../../src/Bridge/Symfony/DependencyInjection/Configuration.php) remains authoritative.

## Discovery and provider

```yaml
jsonapi:
    resource_paths: ['%kernel.project_dir%/src/Entity']
    route_prefix: /api
    data_layer:
        provider: doctrine
    pagination:
        default_size: 25
        max_size: 100
```

The custom provider supports service IDs for `repository`, `processor`, `relationship_reader` and `transaction_manager`. See [data-layer configuration](data-layer-configuration.md). An omitted custom service uses its configured fallback; it does not create application persistence automatically.

## Request and read limits

| Option under `limits` | Current default |
| --- | ---: |
| `filter_max_depth` | 8 |
| `filter_max_nodes` | 100 |
| `filter_max_operands` | 200 |
| `include_max_depth` | 3 |
| `include_max_paths` | 20 |
| `fields_max_total` | 120 |
| `page_max_size` | 100 |
| `included_max_resources` | 1000 |
| `relationship_max_identifiers` | 10000 |
| `complexity_budget` | 200 |

These values must be nonnegative; zero disables the corresponding configurable guard. Filter AST limits run before repository execution. Included resources and linkage identifiers have separate budgets. Choose values for your application graph rather than assuming defaults fit every deployment.

## Relationship and sorting policies

```yaml
jsonapi:
    relationships:
        linkage_in_resource: always
        unplanned_read_policy: legacy
        write_response: linkage
    write:
        allow_relationship_writes: false
    performance:
        doctrine:
            collection_sort_policy: legacy
```

Linkage choices are `always`, `when_included`, `never`; unplanned reads and collection sorting each accept `legacy` or `reject`. Relationship write responses accept `linkage` or the string `'204'`. The [production policy](production-policies.md) shows a stricter starting configuration. Do not change these defaults silently during freeze.

## Cache, profiles and literal type keys

Cache defaults enable hash ETags and required If-Match on writes. `cache.etag.strategy: version` requires the application's current `X-Resource-Version`; a missing version does not fall back to a hash. `cache.last_modified.collections_max_of: false` suppresses inferred collection Last-Modified, while explicit headers remain authoritative.

Literal resource names such as `feature-memos` are preserved in `profiles.per_type`, `cache.last_modified.per_type` and `write.client_generated_ids`. Default type profiles apply in the type's own context; they are not global activation for unrelated types.

Review `cache.headers.public` explicitly: the current default is true. An authenticated or tenant-scoped API needs an application-reviewed cache policy. The [developer path](developer-path.md) starts with private responses.

## Pending reference work

TODO before freeze: complete the node-by-node reference for media channels, Atomic/lid settings, profile configuration, docs/OpenAPI/JSON Schema, serializer contexts and performance caches. Validate all examples with Symfony Configuration, document deprecations and attach migration notes to accepted default changes. Track this in [documentation TODO](../release/documentation-todo.md).

## Effective options and deprecated placeholders

Generated requests and responses apply `media_types.default` request/response settings; explicit acceptable negotiation selects the response type. The legacy `media_type` alias remains deprecated but effective. `performance.head_enabled: false` rejects HEAD on generated resource endpoints, including Symfony's automatic HEAD-to-GET matching; OPTIONS stops advertising HEAD.

`profiles.rel_counts.relationship_meta_key` names relationship count metadata. `compute_in_related_endpoints: false` suppresses both the count fetch requirement and its output for related representations. Resource-level relationshipPolicies default each relationship's linking policy; an explicit relationship attribute wins.

The config-only `dx` section, `errors.locale`, and Doctrine options `enable_query_cache`, `query_cache_pool`, `enable_second_level_cache`, `hydrate_partial_by_fields`, `default_fetch` were removed before 1.0 because they had no runtime effect. Configuring them now fails container configuration validation. Remove them and configure application tooling and ORM caches/fetch metadata directly. Active `head_enabled` and `collection_sort_policy` are retained.
