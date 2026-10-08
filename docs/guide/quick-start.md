# Quick start

This path uses Symfony 7.4, PHP 8.2+, PostgreSQL and the built-in Doctrine provider. Use the same resource API on Symfony 8.x/PHP 8.4.1+, but install DBAL `^4.3` for that target.

```bash
composer create-project symfony/skeleton:"7.4.*" my-api
cd my-api
composer require alexfigures/symfony-jsonapi-bundle doctrine/orm:"^3.0" doctrine/dbal:"^3.8" doctrine/doctrine-bundle doctrine/doctrine-migrations-bundle
composer require --dev symfony/maker-bundle
```

During stabilization select the available bundle development/RC constraint as explained in [installation](installation.md). Configure Doctrine's connection in the application's `.env.local` and keep that file untracked:

```dotenv
DATABASE_URL="postgresql://app:password@127.0.0.1:5432/app?serverVersion=16&charset=utf8"
```

Apply the two short configuration files from [installation](installation.md). Ensure Doctrine maps `App\Entity` using attributes; DoctrineBundle's recipe normally sets this up.

A minimal mapped entity in `src/Entity/Article.php`:

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use AlexFigures\JsonApi\Resource\Attribute\Attribute as ApiAttribute;
use AlexFigures\JsonApi\Resource\Attribute\Id as ApiId;
use AlexFigures\JsonApi\Resource\Attribute\JsonApiResource;
use AlexFigures\JsonApi\Resource\Definition\ResourceOperation;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[JsonApiResource(
    type: 'articles',
    operations: [ResourceOperation::INDEX, ResourceOperation::SHOW,
        ResourceOperation::CREATE, ResourceOperation::UPDATE, ResourceOperation::DELETE],
)]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiId]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[ApiAttribute]
    #[Assert\NotBlank]
    public string $title = '';

    public function getId(): ?int
    {
        return $this->id;
    }
}
```


Generate and apply a migration, then start PHP's development server:

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console make:migration
php bin/console doctrine:migrations:migrate --no-interaction
php -S localhost:8000 -t public
```

```bash
curl -H 'Accept: application/vnd.api+json' http://localhost:8000/api/articles
curl -X POST -H 'Accept: application/vnd.api+json' \
  -H 'Content-Type: application/vnd.api+json' \
  --data '{"data":{"type":"articles","attributes":{"title":"First article"}}}' \
  http://localhost:8000/api/articles
```

The collection contains JSON:API `data`; POST returns the created resource with a stable string `id`. Read the returned resource URL to obtain its ETag before PATCH/DELETE when required preconditions are enabled. Continue with [resources](resource-discovery.md), [CRUD and validation](crud.md), and the [developer path](developer-path.md).
