# JsonApiBundle

A JSON:API 1.1 bundle for Symfony, with Doctrine ORM or custom persistence, generated resource routes, relationships, profiles and Atomic Operations.

**Status:** 1.0 stabilization; no stable 1.0 release is declared yet. Known runtime gaps have independent consumer proof for a recorded revision. Later stabilization/RC revisions require fresh platform verification.

## Requirements and installation

PHP 8.2+ with Symfony 7.4; PHP 8.4.1+ for Symfony 8.x. Target lines are Symfony 7.4 LTS, 8.1 and 8.2 (development until released). The optional Doctrine provider targets ORM 3 / DBAL 3.8+ or 4.3+. [Compatibility policy and evidence](docs/release/compatibility.md) distinguish resolvable dependencies from officially verified support.

```bash
composer require alexfigures/symfony-jsonapi-bundle
```

Use an available development/RC constraint during stabilization. Register the bundle, configure Doctrine and import the generated routes as shown in [installation](docs/guide/installation.md).

Flex detects the root Bundle. Without Flex, add this entry to `config/bundles.php`:

```php
AlexFigures\JsonApi\JsonApiBundle::class => ['all' => true],
```

```php
use AlexFigures\JsonApi\Resource\Attribute\JsonApiResource;

#[JsonApiResource(type: 'articles')]
class Article
{
    // Add Doctrine mapping, one API ID and exposed attributes.
}
```

The [quick start](docs/guide/quick-start.md) supplies the complete runnable entity, configuration, migration, first GET and first POST.

## Core capabilities

- Resource CRUD, linkage/related endpoints, includes and sparse fields.
- Whitelisted filters/sorts, distinct-root pagination and bounded native relationship loading.
- Validation/errors, media negotiation, cache validators and concurrent write preconditions.
- Single-boundary Atomic Operations with rollback and generated-ID/lid workflows.
- DI profiles/hooks, DTO representations/input, custom handlers/routes/providers and OpenAPI.

Applications own authorization policy, database topology and custom-provider cost. Distributed transactions, sharding/tenant frameworks and replication management are outside the contract.

[Documentation](docs/index.md) · [Public API](docs/api/public-api.md) · [Support contract](docs/reference/support-contract.md) · [BC policy](docs/api/bc-policy.md) · [Upgrade to 1.0](UPGRADE-1.0.md) · [Changelog](CHANGELOG.md)

The independent [example application](https://github.com/AlexFigures/example-jsonapi-bundle) provides black-box consumer verification and is maintained separately. [Release gates](docs/release/checklist.md) record exact revisions and required compatibility reruns.

Contributors: [CONTRIBUTING](CONTRIBUTING.md), [TESTING](TESTING.md). Security reports: [SECURITY](SECURITY.md). Licensed under [MIT](LICENSE).
