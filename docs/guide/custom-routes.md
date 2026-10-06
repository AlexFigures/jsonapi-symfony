# Application endpoints

[JsonApiCustomRoute](../../src/Resource/Attribute/JsonApiCustomRoute.php) declares application endpoints beyond CRUD. It can select a handler or controller and configure methods, paths, defaults and resource type. Inspect the actual constructor before using named arguments during stabilization.

Custom actions need explicit application authorization and query-parameter policy. Their permitted application parameters must reach the handler without bypassing validation of reserved JSON:API parameters. A custom read model may disable SHOW; response construction must not require an unavailable item route.

Use the [response factory](response-factory.md) when a controller needs bundle document formatting. Handler transactions do not provide cross-database atomicity; retain the [single-boundary policy](production-policies.md).

TODO before freeze: provide a compiled-container runnable action example with query parameters, input DTO validation, negotiated version representation, operation restrictions and error responses. Audit route/channel selection by path, route name and route attributes.
