# Profiles, DTOs and HTTP caching

Profiles implement [ProfileInterface](../../src/Profile/ProfileInterface.php) and are container services, so constructor DI is supported. Their read/write/relationship/document hooks operate in a type-scoped ProfileContext. `profiles.per_type` defaults can apply without explicit Accept negotiation; keys retain literal resource names.

ReadHook conditions must affect all applicable reads, including target visibility. A RelationshipHook prohibition must stop the mutation. Fetch-plan hooks declare identifiers/models/counts needed for bounded reads. [Production policies](production-policies.md) explain the limits of undeclared callback SQL.

The builtin audit profile can expose `data.meta.audit` through `expose_in_meta`; constructor DI overrides must retain bundle settings unless explicitly overridden. Soft-delete visibility, query flags, boolean/timestamp strategies and delete semantics require consistent query/write configuration.

Resource version resolvers select effective representation definitions using current request context. Normal GET item/collection and sparse responses must all respect the selected DTO. Configured input DTOs and serializer write groups constrain writes independently from read projection.

Hash/version ETags, If-Match and Last-Modified are configured under `cache`. Version mode requires a current version supplied by the application. Disabling inferred collection Last-Modified does not discard an explicit header. Surrogate keys should reflect generated routes and configured type policy.

## Activation and options

```yaml
jsonapi:
    profiles:
        per_type:
            articles: ['urn:jsonapi:profile:audit-trail']
        audit_trail:
            created_at: createdAt
            updated_at: updatedAt
            created_by: createdBy
            updated_by: updatedBy
            expose_in_meta: true
        soft_delete:
            field: deletedAt
            strategy: timestamp
            default_visibility: exclude
            delete_semantics: soft
        rel_counts:
            relationship_meta_key: count
            compute_in_related_endpoints: true
```

Negotiate explicitly with `Accept: application/vnd.api+json;profile="urn:jsonapi:profile:audit-trail"`. Profile hooks/requirements are services; [DI examples](../api/extension-examples.md) and the [full option reference](../reference/configuration.md) define activation and field semantics. Audit user providers and soft-delete actors are application-owned. Soft-delete flags use the configured names; boolean strategy uses false/true semantics rather than timestamp IS NULL.

GET an item and save its ETag; PATCH/DELETE send `If-Match` with that validator. Missing required header is 428, stale value is 412; `*` retains existence semantics. Doctrine re-evaluates after locking the current root, so stale concurrent writers do not both succeed. Version mode uses the application version header; hash mode hashes the representation. Include application authorization/locale/profile variation in shared-cache policy. [Error contract](../api/errors.md) and [production policies](production-policies.md) state the guarantees.

## Validate profile requirements

```bash
php bin/console jsonapi:validate-profiles
```

The command checks enabled profile assignments against resource fields and reports errors/warnings. Required-field errors fail validation; warnings alone do not. Container/profile construction also validates requirements: profiles requiring constructor DI are validated as constructed services, rather than instantiated without dependencies. This is configuration validation, separate from Symfony validation of incoming write DTOs.

Declare field requirements through the public profile requirements model and register custom profiles as services. See [extension examples](../api/extension-examples.md) and [the public profile API](../api/public-api.md).
