# Profiles, DTOs and HTTP caching

Profiles implement [ProfileInterface](../../src/Profile/ProfileInterface.php) and are container services, so constructor DI is supported. Their read/write/relationship/document hooks operate in a type-scoped ProfileContext. `profiles.per_type` defaults can apply without explicit Accept negotiation; keys retain literal resource names.

ReadHook conditions must affect all applicable reads, including target visibility. A RelationshipHook prohibition must stop the mutation. Fetch-plan hooks declare identifiers/models/counts needed for bounded reads. [Production policies](production-policies.md) explain the limits of undeclared callback SQL.

The builtin audit profile can expose `data.meta.audit` through `expose_in_meta`; constructor DI overrides must retain bundle settings unless explicitly overridden. Soft-delete visibility, query flags, boolean/timestamp strategies and delete semantics require consistent query/write configuration.

Resource version resolvers select effective representation definitions using current request context. Normal GET item/collection and sparse responses must all respect the selected DTO. Configured input DTOs and serializer write groups constrain writes independently from read projection.

Hash/version ETags, If-Match and Last-Modified are configured under `cache`. Version mode requires a current version supplied by the application. Disabling inferred collection Last-Modified does not discard an explicit header. Surrogate keys should reflect generated routes and configured type policy.

TODO before freeze: complete negotiated profile examples, builtin profile option tables, hook registration and cache validator recipes against executable bundle fixtures. Freeze defaults only after independent consumer confirmation.
