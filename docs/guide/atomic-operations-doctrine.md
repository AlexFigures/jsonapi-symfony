# Atomic operations with Doctrine

Atomic execution uses the JSON:API Atomic extension media type and an ordered `atomic:operations` document. Enable Atomic in application configuration and explicitly register its endpoint through the supported routing integration; do not assume the ordinary resource route loader publishes an Atomic URL. Inspect `debug:router` in the application.

The complete batch is preflighted before the first mutation. The builtin adapter requires one manager/connection boundary. Cross-database/shard execution and separate managers sharing one connection are unsupported and return 409. No distributed commit is attempted.

Per-operation flushes may make generated IDs available for later lid references inside the same transaction. A flush is not a commit: later failure rolls back prior changes, and one commit follows successful completion of the entire batch. Unrelated managers are not begun/flushed/committed.

The `lid.accept_in_resource_and_identifier` option controls acceptance in resource/identifier positions. Non-ORM typed single writes are not automatically eligible for Doctrine Atomic. [Production policies](production-policies.md) and the [support contract](../reference/support-contract.md) describe these guarantees.

## Endpoint and payload

```yaml
# config/packages/jsonapi.yaml
jsonapi:
    atomic:
        enabled: true
        endpoint: /api/operations

# config/routes/atomic.yaml
jsonapi_atomic:
    path: /api/operations
    controller: AlexFigures\JsonApi\Bridge\Symfony\Controller\AtomicController
    methods: [POST]
```

`AtomicController` is the supported public route callable; its constructor is DI wiring. Keep the route path and atomic.endpoint aligned. The regular resource route import does not create this endpoint.

```json
{"atomic:operations":[
  {"op":"add","data":{"type":"articles","lid":"new-article","attributes":{"title":"First"}}},
  {"op":"update","ref":{"type":"articles","lid":"new-article"},"data":{"type":"articles","attributes":{"title":"Updated"}}}
]}
```

Use `Content-Type` and `Accept` of `application/vnd.api+json;ext="https://jsonapi.org/ext/atomic"`. An existing remove uses `{"op":"remove","ref":{"type":"articles","id":"1"}}`. Invalid operation structure is a controlled client error; unsupported boundaries/conflicts are 409. Ordinary input validation is 422, but certain Atomic handler conflicts intentionally remain 409 with rollback preserved. This is the explicit error contract, not a transaction safety defect. [Atomic integration](../../tests/Integration/Atomic) covers generated IDs, lid references, later rollback and separate connections; [errors](../api/errors.md) documents status/source stability.
