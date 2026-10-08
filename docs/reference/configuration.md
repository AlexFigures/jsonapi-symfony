# Configuration reference

Generated from the active Symfony tree. Run `php scripts/configuration-reference.php` to regenerate; `--check` rejects drift. All paths are below `jsonapi:`. Active nodes are public configuration; removed options fail validation. Only `media_type` remains a deprecated legacy alias.

Scope is global unless the path contains `<type>` or `<channel>`. Type keys preserve literal resource names (including hyphens). Arrays merge according to Symfony configuration processing; unknown keys fail. Resource attributes and per-type profile/field maps provide the documented resource-specific overrides.

| Path | Type / validation | Default | Meaning |
| --- | --- | --- | --- |
| `strict_content_negotiation` | boolean | `true` | Apply strict JSON:API media negotiation; configured channels/default policy still define allowed types. |
| `media_types.default.request.allowed` | list | `["application/vnd.api+json"]` | Allowed incoming media types for this policy. |
| `media_types.default.response.default` | scalar | `"application/vnd.api+json"` | Default response media type when no accepted alternative is selected. |
| `media_types.default.response.negotiable` | list | `[]` | Negotiable response media type list. |
| `media_types.channels` | map | `[]` | Map of named media policies; route/channel scope chooses overrides. |
| `media_types.channels.<name>.scope.path_prefix` | scalar | `null` | Match a URL prefix; null leaves this matcher unspecified. |
| `media_types.channels.<name>.scope.route_name` | scalar | `null` | Route-name regular expression; null leaves it unspecified. |
| `media_types.channels.<name>.scope.attribute` | scalar | `null` | MediaChannel attribute regular expression; null leaves it unspecified. |
| `media_types.channels.<name>.request.allowed` | list | `["application/vnd.api+json"]` | Allowed incoming media types for this policy. |
| `media_types.channels.<name>.response.default` | scalar | `"application/vnd.api+json"` | Default response media type when no accepted alternative is selected. |
| `media_types.channels.<name>.response.negotiable` | list | `[]` | Negotiable response media type list. |
| `media_type` | scalar | `null` | Legacy single media type shortcut; null uses the canonical media_types policy. DEPRECATED: use media_types instead. |
| `route_prefix` | scalar | `"/api"` | Default prefix for generated routes; a resource routePrefix overrides it. |
| `resource_paths` | list | `["%kernel.project_dir%/src/Entity"]` | Directories for attribute discovery; tagged resource services can also register resources. |
| `data_layer.provider` | enum: doctrine, custom | `"doctrine"` | Built-in Doctrine or explicitly configured custom provider. |
| `data_layer.repository` | scalar | `null` | Custom ResourceRepository service ID; null uses provider wiring. |
| `data_layer.processor` | scalar | `null` | Custom ResourceProcessor service ID; null uses provider wiring. |
| `data_layer.relationship_reader` | scalar | `null` | Custom endpoint RelationshipReader service ID. |
| `data_layer.transaction_manager` | scalar | `null` | Custom transaction manager service ID; custom providers own guarantees. |
| `pagination.default_size` | integer; min 1 | `25` | Default root/related page size; positive. |
| `pagination.max_size` | integer; min 1 | `100` | Maximum configured page size; positive; independent limits.page_max_size also applies. |
| `write.allow_relationship_writes` | boolean | `false` | Enable relationship mutation endpoints. |
| `write.client_generated_ids` | map | `[]` | Map of resource types permitting client-generated IDs; unspecified types do not opt in. |
| `relationships.write_response` | enum: linkage, 204 | `"linkage"` | Return linkage or HTTP 204 after a relationship write. |
| `relationships.linkage_in_resource` | enum: never, when_included, always | `"always"` | Emit embedded linkage always, only on included paths, or never. |
| `relationships.unplanned_read_policy` | enum: legacy, reject | `"legacy"` | Legacy getters or reject undeclared relationship loads; custom SQL is application-owned. |
| `errors.expose_debug_meta` | boolean | `false` | Expose configured debugging metadata; leave false for production. |
| `errors.add_correlation_id` | boolean | `true` | Attach correlation identifiers to error responses. |
| `errors.default_title_map` | boolean | `true` | Use the built-in title-category map. |
| `cache.enabled` | boolean | `true` | Enable bundle cache header/conditional processing. |
| `cache.etag.strategy` | enum: hash, version | `"hash"` | Hash representation or use application-provided version validator without hash fallback. |
| `cache.etag.hash_algo` | scalar | `"xxh3"` | PHP hash algorithm used for hash strategy. |
| `cache.etag.weak_for_collections` | boolean | `true` | Emit weak collection ETags. |
| `cache.etag.include_query_shape` | boolean | `true` | Include representation query shape in generated validator input. |
| `cache.last_modified.resource_field` | scalar | `"updatedAt"` | Default resource timestamp property. |
| `cache.last_modified.per_type` | map | `[]` | Literal type-to-timestamp-property overrides. |
| `cache.last_modified.collections_max_of` | boolean | `true` | Synthesize collection Last-Modified from resource maxima; false omits it. |
| `cache.headers.public` | boolean | `true` | Public versus private cache policy; applications own authorization/variation. |
| `cache.headers.max_age` | integer; min 0 | `30` | Browser freshness in seconds; zero is zero seconds. |
| `cache.headers.s_maxage` | integer; min 0 | `600` | Shared-cache freshness in seconds. |
| `cache.headers.stale_while_revalidate` | integer; min 0 | `60` | Permitted stale interval while revalidating, in seconds. |
| `cache.headers.stale_if_error` | integer; min 0 | `300` | Permitted stale interval on error, in seconds. |
| `cache.headers.add_age` | boolean | `true` | Add response Age header. |
| `cache.vary.accept` | boolean | `true` | Vary cache responses by Accept. |
| `cache.vary.accept_language` | boolean | `false` | Vary cache responses by Accept-Language. |
| `cache.surrogate_keys.enabled` | boolean | `true` | Publish surrogate keys; purge infrastructure is application-owned. |
| `cache.surrogate_keys.header_name` | scalar | `"Surrogate-Key"` | Response header carrying surrogate keys. |
| `cache.surrogate_keys.format.resource` | scalar | `"{type}:{id}"` | Template for resource key, using type/id. |
| `cache.surrogate_keys.format.collection` | scalar | `"{type}"` | Template for collection key, using type. |
| `cache.surrogate_keys.format.relationship` | scalar | `"{type}:{id}:{rel}"` | Template for relationship key, using type/id/rel. |
| `cache.conditional.enable_if_none_match` | boolean | `true` | Evaluate If-None-Match on reads. |
| `cache.conditional.enable_if_modified_since` | boolean | `true` | Evaluate If-Modified-Since on reads. |
| `cache.conditional.enable_if_match` | boolean | `true` | Evaluate If-Match on writes; Doctrine guard protects current root. |
| `cache.conditional.enable_if_unmodified_since` | boolean | `true` | Evaluate If-Unmodified-Since when configured. |
| `cache.conditional.require_if_match_on_write` | boolean | `true` | Require If-Match on update/delete; missing header returns 428. |
| `limits.include_max_depth` | integer; min 0 | `3` | Maximum include path depth; zero disables. |
| `limits.filter_max_depth` | integer; min 0 | `8` | Maximum structural/raw filter depth before SQL; zero disables. |
| `limits.filter_max_nodes` | integer; min 0 | `100` | Maximum AST nodes before SQL; zero disables. |
| `limits.filter_max_operands` | integer; min 0 | `200` | Maximum operands including every IN/NOT IN value; zero disables. |
| `limits.include_max_paths` | integer; min 0 | `20` | Maximum distinct include paths; zero disables. |
| `limits.fields_max_total` | integer; min 0 | `120` | Maximum requested sparse fields across types; zero disables. |
| `limits.page_max_size` | integer; min 0 | `100` | Independent hard requested page-size limit; zero disables this guard only. |
| `limits.included_max_resources` | integer; min 0 | `1000` | Maximum included identities, reserved before native hydration; zero disables. |
| `limits.relationship_max_identifiers` | integer; min 0 | `10000` | Maximum relationship identifiers per document/standalone linkage; zero disables. |
| `limits.complexity_budget` | integer; min 0 | `200` | Weighted include/fields/page/filter complexity budget; zero disables. |
| `performance.doctrine.collection_sort_policy` | enum: reject, legacy | `"legacy"` | Legacy joined to-many ordering or reject without an explicit aggregate sort handler. |
| `performance.head_enabled` | boolean | `true` | Enable generated HEAD; false rejects HEAD and omits it from OPTIONS. |
| `docs.generator.openapi.enabled` | boolean | `true` | Expose generated OpenAPI endpoint. |
| `docs.generator.openapi.route` | scalar | `"/_jsonapi/openapi.json"` | OpenAPI specification route path. |
| `docs.generator.openapi.title` | scalar | `"My API"` | Generated specification title. |
| `docs.generator.openapi.version` | scalar | `"1.0.0"` | Application API version in the document; not bundle release version. |
| `docs.generator.openapi.servers` | list | `["https://api.example.com"]` | Server URL list for generated specification. |
| `docs.generator.json_schema.enabled` | boolean | `true` | Expose generated JSON Schema endpoint. |
| `docs.generator.json_schema.route` | scalar | `"/_jsonapi/schemas"` | JSON Schema route path. |
| `docs.generator.json_schema.include_profiles` | boolean | `true` | Include profile-related schema information. |
| `docs.ui.enabled` | boolean | `true` | Expose documentation UI endpoint. |
| `docs.ui.route` | scalar | `"/_jsonapi/docs"` | UI route path. |
| `docs.ui.spec_url` | scalar | `"/_jsonapi/openapi.json"` | Specification URL used by UI. |
| `docs.ui.theme` | enum: swagger, redoc | `"swagger"` | Swagger UI or ReDoc renderer. |
| `atomic.enabled` | boolean | `false` | Enable Atomic endpoint and processing. |
| `atomic.endpoint` | scalar | `"/api/operations"` | Atomic route path; not derived from route_prefix. |
| `atomic.require_ext_header` | boolean | `true` | Require Atomic extension negotiation. |
| `atomic.max_operations` | integer; min 1 | `100` | Maximum operations per batch; positive. |
| `atomic.return_policy` | enum: auto, none, always | `"auto"` | Automatic, no result data, or always return available result data. |
| `atomic.allow_href` | boolean | `true` | Accept href operation references. |
| `atomic.lid.accept_in_resource_and_identifier` | boolean | `true` | Accept local IDs in Atomic resource/identifier data; false rejects them. |
| `profiles.negotiation.require_known_profiles` | boolean | `false` | Reject unknown requested profiles when enabled. |
| `profiles.negotiation.echo_profiles_in_content_type` | boolean | `true` | Publish activated profile URIs in Content-Type. |
| `profiles.negotiation.link_header` | boolean | `true` | Publish profile link headers. |
| `profiles.enabled_by_default` | list | `[]` | Global default profile URI list; activated without explicit negotiation. |
| `profiles.per_type` | map | `[]` | Map resource type to default profile URI lists; activation is type-scoped. |
| `profiles.per_type.<type>` | list | `[]` | Profile URI list for this literal resource type. |
| `profiles.soft_delete.field` | scalar | `"deletedAt"` | Property recording deletion timestamp/boolean. |
| `profiles.soft_delete.strategy` | enum: timestamp, boolean | `"timestamp"` | Interpret field as nullable timestamp or boolean deletion marker. |
| `profiles.soft_delete.default_visibility` | enum: exclude, include, only | `"exclude"` | Exclude, include, or show only soft-deleted rows. |
| `profiles.soft_delete.query_flags.with_deleted` | scalar | `"withDeleted"` | Application query parameter name to include deleted rows. |
| `profiles.soft_delete.query_flags.only_deleted` | scalar | `"onlyDeleted"` | Application query parameter name to show only deleted rows. |
| `profiles.soft_delete.delete_semantics` | enum: soft, hard | `"soft"` | Soft mark or physical delete while the profile is active. |
| `profiles.audit_trail.created_at` | scalar | `"createdAt"` | Creation time property; attribute overrides are supported. |
| `profiles.audit_trail.updated_at` | scalar | `"updatedAt"` | Update time property; attribute overrides are supported. |
| `profiles.audit_trail.created_by` | scalar | `null` | Creation actor property; null disables it; application user provider supplies actor. |
| `profiles.audit_trail.updated_by` | scalar | `null` | Update actor property; null disables it. |
| `profiles.audit_trail.expose_in_meta` | boolean | `true` | Expose configured audit values in resource.meta.audit. |
| `profiles.rel_counts.relationship_meta_key` | scalar | `"count"` | Non-empty relationship metadata key for computed counts. |
| `profiles.rel_counts.compute_in_related_endpoints` | boolean | `true` | Compute/publish relationship counts on related endpoints. |

Zero disables each `limits.*` guard. Pagination default/max size and Atomic max operations must be positive. Cache duration zero means zero seconds, not unlimited. Native defaults preserve legacy linkage/fallback/to-many sorting; production applications should select `when_included`/`never`, `reject` fallback and explicit collection sort handlers where needed. See [production policies](../guide/production-policies.md).
