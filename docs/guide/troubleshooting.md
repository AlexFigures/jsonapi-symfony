# Troubleshooting

Start by inspecting the resolved configuration, discovered routes and application services:

```bash
php bin/console debug:config jsonapi
php bin/console debug:router
php bin/console debug:container
```

- No generated resource routes: check bundle registration, resource_paths, discovery metadata, enabled operations and the `type: jsonapi` import.
- Discovery failure for a composite Doctrine ID: expose one API identifier or use a custom provider.
- PATCH/DELETE 428: send the required If-Match from a current item response. A stale validator yields 412 and no mutation.
- Atomic 409 for boundary scope: all resources must resolve to one supported manager/connection; independent connections are not supported.
- Query/linkage/include limit rejection: reduce requested work or review application limits. Do not silently truncate a document.
- Unexpected custom-provider behavior: inspect the selected service and supports(type), then verify scope, validation and transaction guarantees.

For changing validators and N+1, compare cold-cache request query shape at different page sizes and inspect declared relationship/profile needs. Arbitrary application getters and SQL are not automatically batch-loaded.

TODO before freeze: expand verified error code/source examples, media negotiation/OpenAPI troubleshooting and diagnostics for strict fallback behavior. See [documentation TODO](../release/documentation-todo.md).
