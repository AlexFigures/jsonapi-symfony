# OpenAPI and JSON Schema

With documentation enabled, the default endpoints are `/_jsonapi/openapi.json`, `/_jsonapi/docs` and `/_jsonapi/schemas`. Configure their routes and enablement under `docs` in the [configuration reference](../reference/configuration.md).

The generator uses discovered resources, enabled operations, query declarations, pagination configuration, serializer write metadata and `OpenApiEndpoint` / `OpenApiExample` attributes. Read schemas retain required non-null JSON:API identity even when an application suppresses an ID attribute. Native schema/UI media types are negotiated on their own routes.

Protect or disable documentation endpoints according to application policy. Generated examples do not replace executable application contracts. The public documentation attributes are classified in the [API manifest](../api/public-api-manifest.json).
