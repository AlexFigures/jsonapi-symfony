# JsonApiBundle

A JSON:API 1.1 bundle for Symfony, with Doctrine ORM or custom persistence, generated resource routes, relationships, profiles and Atomic Operations.

**Status:** preparing final 1.0 after the existing RC. The merged implementation has passed independent application verification across the compatibility targets. See [verification](docs/release/verification.md) for tested revisions and the [release checklist](docs/release/checklist.md) for package publication.

## Requirements and installation

PHP 8.2+ with Symfony 7.4; PHP 8.4.1+ for Symfony 8.x. Supported stable targets are Symfony 7.4 LTS and 8.1; Symfony 8.2-dev is tested for forward compatibility. The optional Doctrine provider targets ORM 3 / DBAL 3.8+ or 4.3+. [Compatibility policy and evidence](docs/release/compatibility.md) distinguish resolvable dependencies from officially verified support.

```bash
composer require alexfigures/symfony-jsonapi-bundle
```

For a published release candidate, use the explicit RC constraint in [installation](docs/guide/installation.md). Register the bundle, configure Doctrine and import the generated routes there.

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
