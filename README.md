# JsonApiBundle

JSON:API resources, relationships, queries, writes, profiles and HTTP caching for Symfony, with a built-in Doctrine ORM provider and custom persistence contracts.

This branch is preparing for 1.0. Bundle regressions and independent consumer acceptance are separate release gates. The public API and supported platform matrix are still being audited.

```bash
composer require alexfigures/symfony-jsonapi-bundle
```

Start with the [developer path](docs/guide/developer-path.md): installation, a Doctrine resource, generated routes, query options, writes and production limits.

| Need | Documentation |
| --- | --- |
| Build an application | [Developer path](docs/guide/developer-path.md) |
| Configure the bundle | [Configuration reference](docs/guide/configuration.md) |
| Extend persistence, relationships or profiles | [Extension contracts](docs/api/public-api.md) |
| Understand transaction, concurrency and read limits | [Production policies](docs/guide/production-policies.md) |
| Migrate an existing integration | [Upgrade to 1.0 draft](UPGRADE-1.0.md) |
| Contribute and run bundle tests | [Contributing](CONTRIBUTING.md), [testing](TESTING.md) |
| Prepare RC / final | [Release checklist](docs/release/checklist.md) |

Composer currently allows PHP `^8.2` and Symfony components `^7.1`; development tooling is resolved for PHP 8.4. CI currently runs PHP 8.4. These constraints are not a tested 1.0 compatibility matrix. See the [compatibility evidence](docs/release/compatibility.md) before choosing release targets.

Atomic execution requires one supported Doctrine manager/connection boundary. Independent connections and separate managers sharing a connection are rejected before mutation. Custom providers must supply their own transaction and concurrency guarantees.

Native Doctrine relationship loading uses a fetch plan and bounded batches. Cost guarantees do not extend automatically to arbitrary getters, custom provider SQL or undeclared hooks. See [production policies](docs/guide/production-policies.md).

[Documentation index](docs/README.md) · [BC policy](docs/api/bc-policy.md) · [License](LICENSE)
