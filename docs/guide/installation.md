# Installation

```bash
composer require alexfigures/symfony-jsonapi-bundle
```

The command installs a published stable release. During the release-candidate phase, select a published RC explicitly:

```bash
composer require alexfigures/symfony-jsonapi-bundle:"^1.0@RC"
```

Available tags determine what Composer can install; preparing this documentation does not publish a package.

The package requires PHP 8.2+ for Symfony 7.4. Symfony 8.x requires PHP 8.4.1+ through its component constraints. Verified stable targets are Symfony 7.4 LTS and 8.1. Symfony 8.2-dev has forward-compatibility verification; its stable release needs a separate promotion. Composer allows `^7.4 || ^8.0`; this does not declare every resolvable Symfony series supported. See [compatibility evidence](../release/compatibility.md).

Doctrine is optional. The built-in provider targets ORM 3.x and DBAL 3.8+ / 4.3+, with PostgreSQL 16 and MySQL 8.0 integration lanes. Symfony 8.1/8.2 require the DBAL 4.3+ lane because current HttpFoundation conflicts with older DBAL. A custom provider can implement the [data contracts](../api/public-api.md).

Symfony Flex’s generated recipe detects the root Bundle by its PSR-4 namespace and class name. Check `config/bundles.php` after installation. Without Flex, register:

```php
AlexFigures\JsonApi\JsonApiBundle::class => ['all' => true],
```

The package type is `symfony-bundle` and its root Bundle follows Flex’s automatic naming convention. No custom recipe is needed for activation. Route import and application-specific configuration are explicit:

```yaml
# config/packages/jsonapi.yaml
jsonapi:
    resource_paths: ['%kernel.project_dir%/src/Entity']
    route_prefix: /api
    data_layer:
        provider: doctrine
    cache:
        headers:
            public: false
```

```yaml
# config/routes/jsonapi.yaml
jsonapi:
    resource: .
    type: jsonapi
```

Install/configure Doctrine in the application before selecting its provider. The bundle does not provision databases or configure topology. Continue with the [quick start](quick-start.md).

Activation behavior was verified against [Flex’s bundle detector](https://github.com/symfony/flex/blob/2.x/src/SymfonyBundle.php) and [auto-generated recipe flow](https://github.com/symfony/flex/blob/2.x/src/Flex.php).
