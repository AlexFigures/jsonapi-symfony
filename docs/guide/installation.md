# Installation

```bash
composer require alexfigures/symfony-jsonapi-bundle
```

During stabilization use an explicit available development revision or RC constraint in your application; the command above becomes the release path when the package is published. It does not imply that 1.0.0 exists today.

The package requires PHP 8.2+ for Symfony 7.4. Symfony 8.x requires PHP 8.4.1+ through its component constraints. Stabilization targets Symfony 7.4 LTS, 8.1 and 8.2 (development lane until release). Composer allows `^7.4 || ^8.0`; official support requires bundle CI and independent application verification for the exact line. See [compatibility evidence](../release/compatibility.md).

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
