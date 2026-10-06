# Atomic operations with Doctrine

Atomic execution uses the JSON:API Atomic extension media type and an ordered `atomic:operations` document. Enable Atomic in application configuration and explicitly register its endpoint through the supported routing integration; do not assume the ordinary resource route loader publishes an Atomic URL. Inspect `debug:router` in the application.

The complete batch is preflighted before the first mutation. The builtin adapter requires one manager/connection boundary. Cross-database/shard execution and separate managers sharing one connection are unsupported and return 409. No distributed commit is attempted.

Per-operation flushes may make generated IDs available for later lid references inside the same transaction. A flush is not a commit: later failure rolls back prior changes, and one commit follows successful completion of the entire batch. Unrelated managers are not begun/flushed/committed.

The `lid.accept_in_resource_and_identifier` option controls acceptance in resource/identifier positions. Non-ORM typed single writes are not automatically eligible for Doctrine Atomic. [Production policies](production-policies.md) and the [support draft](../architecture/support-contract-1.0-draft.md) describe these guarantees.

TODO before freeze: publish a tested endpoint registration/configuration example, add/update/remove/lid payloads and the final error table. ATOMIC-VALIDATION-BOUNDARY (409 versus 422 with rollback preserved) requires an explicit error-contract decision; do not hide it as a transaction failure.
