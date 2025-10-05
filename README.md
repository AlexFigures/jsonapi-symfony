# JsonApiBundle (Stage 0)

A DX-first Symfony 7 bundle scaffold for building fully compliant JSON:API 1.1 backends.

## Installation

```bash
composer require jsonapi/symfony-jsonapi-bundle
```

For local development against this repository:

```bash
git clone https://github.com/your-org/jsonapi-symfony.git
cd jsonapi-symfony
composer install
```

## Usage

Register the bundle in your Symfony application's `config/bundles.php`:

```php
return [
    JsonApi\Symfony\Bridge\Symfony\Bundle\JsonApiBundle::class => ['all' => true],
];
```

Configure the bundle (defaults shown):

```yaml
# config/packages/jsonapi.yaml
jsonapi:
    strict_content_negotiation: true
    media_type: 'application/vnd.api+json'
```

Declare your first resource:

```php
use JsonApi\Symfony\Resource\Attribute\JsonApiResource;
use JsonApi\Symfony\Resource\Attribute\Id;
use JsonApi\Symfony\Resource\Attribute\Attribute;
use JsonApi\Symfony\Resource\Attribute\Relationship;

#[JsonApiResource(type: 'articles')]
final class Article
{
    #[Id]
    public string $id;

    #[Attribute]
    public string $title;

    #[Relationship(toMany: true)]
    public array $comments = [];
}
```

Stage 0 delivers:

* Bundle skeleton with configuration tree and attribute autoconfiguration.
* Strict media-type negotiation stub returning 406/415 according to JSON:API 1.1.
* Foundational DX tooling: PHPUnit, PHPStan, CS Fixer, Rector, Infection.

Subsequent stages will add read/write endpoints, metadata registry, document building, Atomic Operations, and more.
