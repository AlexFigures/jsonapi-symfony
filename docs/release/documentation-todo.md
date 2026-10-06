# Documentation work before RC

Current pages contain source-backed behavior and explicitly incomplete sections. Old setup instructions, duplicate references, speculative examples and historical integration reports have been removed. This list tracks missing documentation, not a claim that its associated runtime behavior is absent.

## Can proceed while external verification runs

| Topic | Remaining work | Verification |
| --- | --- | --- |
| Installation | Runnable consumer fixture for the minimal Doctrine resource and routes | Compiled container, discovery, POST/GET |
| Configuration | Full node/default/deprecation reference, media/profile/docs options | Symfony Configuration processing |
| CRUD and DTOs | Serializer YAML groups, input DTO validation, version resolver examples | Normal and sparse item/collection; write regressions |
| Queries | Full operator/operand table; nested AND/OR, aliases and inherited field declarations | Parser and PostgreSQL/MySQL handler tests |
| Relationships | Native linkage/related paging examples, computed batch and endpoint readers | Scope and budget tests, SQL shape |
| Custom providers | Complete typed repository/processor/persister fixture and registration/priority | Compiled container and mutation failure paths |
| Hooks/profiles | DI, defaults/negotiation, fetch requirements, audit and soft-delete recipes | Profile kernel and integration fixtures |
| Cache | Hash/version lifecycle, current version under lock, surrogate keys, Last-Modified | Sequential/concurrent preconditions and cache fixtures |
| Atomic | Runnable endpoint setup, generated IDs/lids, payload examples | Preflight and later-failure rollback regressions |
| OpenAPI/JSON Schema | Endpoint setup, native media channels, examples and disabled operations | Generated schema/container/controller regressions |
| Errors | Stable HTTP status/code/title/source reference | Error mapper and protocol regressions |
| Conformance | Requirement-to-test mapping, then independent evidence | Bundle and consumer commit/result references |

## Depends on external evidence and freeze decisions

- Final support guarantees and exact SQL-cost envelope for native/custom paths.
- Final defaults for linkage, unplanned reads, collection-valued sorts and limits.
- ATOMIC-VALIDATION-BOUNDARY error status decision and migration impact.
- Accepted namespaces, named attribute arguments and optional extension interfaces.
- Supported platform combinations and required BC baseline.
- Final migration document and RC version examples.

Update topic pages when each item is verified. Do not fill unfinished recipes with speculative code or reinstate unverified conformance percentages. Implementation reports remain internal release evidence while the consumer gate runs; durable application requirements belong in the guides.
