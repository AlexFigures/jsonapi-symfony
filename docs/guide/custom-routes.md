# Application endpoints

[JsonApiCustomRoute](../../src/Resource/Attribute/JsonApiCustomRoute.php) declares application endpoints beyond CRUD. It can select a handler or controller and configure methods, paths, defaults and resource type. Inspect the actual constructor before using named arguments during stabilization.

Custom actions need explicit application authorization and query-parameter policy. Their permitted application parameters must reach the handler without bypassing validation of reserved JSON:API parameters. A custom read model may disable SHOW; response construction must not require an unavailable item route.

Use the [response factory](response-factory.md) when a controller needs bundle document formatting. Handler transactions do not provide cross-database atomicity; retain the [single-boundary policy](production-policies.md).

## Read-only handler example

Declare this on a discovered resource, using its actual type:

```php
#[JsonApiCustomRoute(
    name: 'articles.search',
    path: '/articles/search',
    methods: ['GET'],
    handler: SearchArticles::class,
)]
```

```php
use AlexFigures\JsonApi\CustomRoute\Attribute\NoTransaction;
use AlexFigures\JsonApi\CustomRoute\Context\CustomRouteContext;
use AlexFigures\JsonApi\CustomRoute\Handler\CustomRouteHandlerInterface;
use AlexFigures\JsonApi\CustomRoute\Result\CustomRouteResult;

#[NoTransaction]
final class SearchArticles implements CustomRouteHandlerInterface
{
    public function __construct(private ArticleSearch $search) {}

    public function handle(CustomRouteContext $context): CustomRouteResult
    {
        return CustomRouteResult::collection($this->search->find($context->getRequest()->query->getString('q')));
    }
}
```

Register the handler as an autowired service tagged `jsonapi.custom_route_handler`. Application query parameters such as q reach custom handlers; reserved JSON:API parameters retain validation. `NoTransaction` is appropriate for a read-only handler. Mutation handlers remain subject to scoped persistence/transaction rules. Controller mode is retained; use the public ResponseFactory for formatting. [Custom route tests](../../tests/Integration/Http/Controller/CustomRouteControllerTest.php) and [regression kernel](../../tests/Functional/Regression) verify actual wiring and media/channel/version behavior.
