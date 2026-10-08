# Developer path

Use this sequence to build an application with the current public contract. The independent [example application](https://github.com/AlexFigures/example-jsonapi-bundle) contains a real consumer and black-box tests; it is maintained separately from this bundle.

1. [Install](installation.md) and follow [quick start](quick-start.md) through one entity, migration, GET and POST.
2. Define [resources and discovery](resource-discovery.md), allowed [CRUD operations](crud.md), writable groups and input validation.
3. Add [relationships](relationships.md), then [filters](filterable-fields.md), [sorting](sorting-configuration.md) and [pagination](pagination.md).
4. Design [includes and sparse fields](representations.md). Review [graph visibility and batch loading](relationship-loading.md) before adding computed relationships or repository decorators.
5. Add [profiles, representation versions and caching](advanced-features.md) only where the application needs them. Implement [extension contracts](../api/public-api.md) using [examples](../api/extension-examples.md).
6. Use [custom routes](custom-routes.md) and [ResponseFactory](response-factory.md) for actions beyond generated CRUD; publish [OpenAPI](openapi.md) for the enabled operations.
7. Choose [production policies and limits](production-policies.md), verify [errors](../api/errors.md), and test authorization, scopes and custom-provider costs in the application.

[Configuration reference](../reference/configuration.md) documents the actual options/defaults. [Support contract](../reference/support-contract.md) defines guarantees and responsibilities. [Upgrade to 1.0](../../UPGRADE-1.0.md) records implemented breaking changes; [BC policy](../api/bc-policy.md) defines maintenance after the stable baseline.

For bundle development use [CONTRIBUTING](../../CONTRIBUTING.md) and [TESTING](../../TESTING.md). Publication is controlled by the separate [release checklist](../release/checklist.md); a passing local suite is not independent consumer evidence.
