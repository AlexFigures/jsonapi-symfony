<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Unit\Regression;

use AlexFigures\JsonApi\Atomic\AtomicConfig;
use AlexFigures\JsonApi\Atomic\Parser\AtomicRequestParser;
use AlexFigures\JsonApi\Bridge\Symfony\Negotiation\ChannelScopeMatcher;
use AlexFigures\JsonApi\Bridge\Symfony\Negotiation\ConfigMediaTypePolicyProvider;
use AlexFigures\JsonApi\Bridge\Symfony\Routing\JsonApiRouteLoader;
use AlexFigures\JsonApi\Contract\Data\RelationshipReader;
use AlexFigures\JsonApi\Contract\Data\SliceIds;
use AlexFigures\JsonApi\Docs\OpenApi\OpenApiSpecGenerator;
use AlexFigures\JsonApi\Filter\Ast\Comparison;
use AlexFigures\JsonApi\Filter\Ast\NullCheck;
use AlexFigures\JsonApi\Filter\Operator\AbstractOperator;
use AlexFigures\JsonApi\Filter\Operator\DoctrineExpression;
use AlexFigures\JsonApi\Filter\Operator\Registry;
use AlexFigures\JsonApi\Filter\Parser\FilterParser;
use AlexFigures\JsonApi\Http\Cache\SurrogateKeyBuilder;
use AlexFigures\JsonApi\Http\Error\ErrorBuilder;
use AlexFigures\JsonApi\Http\Error\ErrorMapper;
use AlexFigures\JsonApi\Http\Exception\BadRequestException;
use AlexFigures\JsonApi\Http\Relationship\LinkageBuilder;
use AlexFigures\JsonApi\Http\Request\FilteringWhitelist;
use AlexFigures\JsonApi\Http\Request\PaginationConfig;
use AlexFigures\JsonApi\Http\Request\QueryParser;
use AlexFigures\JsonApi\Http\Request\SortingWhitelist;
use AlexFigures\JsonApi\Profile\Builtin\SoftDeleteProfile;
use AlexFigures\JsonApi\Profile\ProfileContext;
use AlexFigures\JsonApi\Resource\Definition\ResourceOperation;
use AlexFigures\JsonApi\Resource\Registry\ResourceRegistry;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\Article;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\Author;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\Tag;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class RcExtensionContractTest extends TestCase
{
    private function errors(): ErrorMapper
    {
        return new ErrorMapper(new ErrorBuilder(false));
    }

    public function testRegisteredCustomOperatorPassesParserAndWhitelist(): void
    {
        $operator = new class () extends AbstractOperator {
            public function name(): string
            {
                return 'starts_with';
            }
            public function compile(string $rootAlias, string $dqlField, array $values, AbstractPlatform $platform): DoctrineExpression
            {
                return new DoctrineExpression($dqlField . ' LIKE :prefix', ['prefix' => $values[0] . '%']);
            }
        };
        $registry = new ResourceRegistry([Article::class]);
        $metadata = $registry->getByType('articles');
        $metadata->filterableFields = new \AlexFigures\JsonApi\Resource\Attribute\FilterableFields([new \AlexFigures\JsonApi\Resource\Attribute\FilterableField('title', operators: ['starts_with'])]);
        $parser = new QueryParser($registry, new PaginationConfig(), new SortingWhitelist($registry), new FilteringWhitelist($registry, $this->errors()), $this->errors(), new FilterParser(8, new Registry([$operator])));
        $criteria = $parser->parse('articles', Request::create('/api/articles?filter[title][starts_with]=Hello'));
        self::assertInstanceOf(Comparison::class, $criteria->filter);
        self::assertSame('starts_with', $criteria->filter->operator);
        self::assertSame(['Hello'], $criteria->filter->values);
    }

    #[DataProvider('nullOperators')]
    public function testPublicNullOperators(string $operator, string $value, bool $null): void
    {
        $node = (new FilterParser())->parse(['title' => [$operator => $value]]);
        self::assertInstanceOf(NullCheck::class, $node);
        self::assertSame($null, $node->isNull);
    }

    public static function nullOperators(): iterable
    {
        yield ['null', 'true', true];
        yield ['nnull', 'true', false];
        yield ['null', 'false', false];
        yield ['nnull', 'false', true];
        yield ['isnull', 'true', true];
    }

    #[DataProvider('lidDocuments')]
    public function testDisabledLidsRejectBeforeExecution(array $operation, string $pointer): void
    {
        $parser = new AtomicRequestParser(new AtomicConfig(enabled: true, lidInResourceAndIdentifier: false), $this->errors());
        try {
            $parser->parse(Request::create('/api/operations', 'POST', content: json_encode(['atomic:operations' => [$operation]], \JSON_THROW_ON_ERROR)));
            self::fail('Configured local identifier rejection must happen in the parser.');
        } catch (BadRequestException $error) {
            self::assertSame($pointer, $error->getErrors()[0]->source?->pointer);
        }
    }

    public static function lidDocuments(): iterable
    {
        yield [['op' => 'add', 'ref' => ['type' => 'articles'], 'data' => ['type' => 'articles', 'lid' => 'a']], '/atomic:operations/0/data/lid'];
        yield [['op' => 'remove', 'ref' => ['type' => 'articles', 'lid' => 'a']], '/atomic:operations/0/ref/lid'];
        yield [['op' => 'add', 'ref' => ['type' => 'articles'], 'data' => ['type' => 'articles', 'relationships' => ['tags' => ['data' => [['type' => 'tags', 'lid' => 't']]]]]], '/atomic:operations/0/data/relationships/tags/data/0/lid'];
    }

    public function testStandaloneLinkageProbesBudgetWithoutTruncation(): void
    {
        $registry = new ResourceRegistry([Article::class, Tag::class, Author::class]);
        $reader = $this->createMock(RelationshipReader::class);
        $reader->expects(self::once())->method('getToManyIds')->with('articles', 'a', 'tags', self::callback(static fn ($page): bool => $page->size === 3))->willReturn(new SliceIds(['1', '2', '3'], 1, 3, 50000));
        $this->expectException(BadRequestException::class);
        (new LinkageBuilder($registry, $reader, new PaginationConfig(), 2))->read('articles', 'a', 'tags', Request::create('/api/articles/a/relationships/tags'));
    }

    public function testGeneratedRoutesProduceConfiguredSurrogateKeys(): void
    {
        $request = Request::create('/api/articles/1/relationships/tags');
        $request->attributes->add(['_route' => 'jsonapi.articles.relationships.tags.show', 'type' => 'articles', 'id' => '1', 'rel' => 'tags']);
        $keys = new SurrogateKeyBuilder(['format' => ['resource' => 'r:{type}:{id}', 'collection' => 'c:{type}', 'relationship' => 'rel:{type}:{id}:{rel}']]);
        self::assertSame(['c:articles', 'r:articles:1', 'rel:articles:1:tags'], $keys->build($request));
        $request->attributes->remove('id');
        $request->attributes->remove('rel');
        self::assertSame(['c:articles'], $keys->build($request));
    }

    public function testOpenApiUsesConfiguredOperationsPaginationAndWriteGroups(): void
    {
        $registry = new ResourceRegistry([Article::class, Author::class, Tag::class]);
        $registry->getByType('authors')->allowedOperations = [];
        $registry->getByType('articles')->allowedOperations = [ResourceOperation::INDEX, ResourceOperation::SHOW];
        $spec = (new OpenApiSpecGenerator($registry, null, ['enabled' => true, 'route' => '/spec', 'title' => 'API', 'version' => '1', 'servers' => []], '/api', 'linkage', pagination: new PaginationConfig(5, 20)))->generate();
        self::assertArrayNotHasKey('/api/authors', $spec['paths']);
        self::assertArrayNotHasKey('/api/authors/{id}', $spec['paths']);
        self::assertArrayNotHasKey('post', $spec['paths']['/api/articles']);
        self::assertArrayNotHasKey('patch', $spec['paths']['/api/articles/{id}']);
        self::assertArrayNotHasKey('patch', $spec['paths']['/api/articles/{id}/relationships/tags']);
        $size = array_values(array_filter($spec['paths']['/api/articles']['get']['parameters'], static fn (array $p): bool => $p['name'] === 'page[size]'))[0];
        self::assertSame(5, $size['schema']['default']);
        self::assertSame(20, $size['schema']['maximum']);
        self::assertTrue($spec['components']['schemas']['ArticlesResource']['properties']['attributes']['properties']['createdAt']['readOnly']);
    }

    public function testSoftProfileFlagsAreConsumedOnlyWhenActive(): void
    {
        $registry = new ResourceRegistry([Article::class]);
        $parser = new QueryParser($registry, new PaginationConfig(), new SortingWhitelist($registry), new FilteringWhitelist($registry, $this->errors()), $this->errors(), new FilterParser());
        $request = Request::create('/api/articles?filter[includeArchived]=true');
        $profile = new SoftDeleteProfile(['query_flags' => ['with_deleted' => 'includeArchived']]);
        ProfileContext::store($request, new ProfileContext([$profile->uri() => $profile]));
        $criteria = $parser->parse('articles', $request);
        self::assertNull($criteria->filter);
        self::assertSame([], $criteria->customConditions);
        self::assertSame('true', $request->query->all('filter')['includeArchived']);
        $request->attributes->remove(ProfileContext::REQUEST_ATTRIBUTE);
        $this->expectException(BadRequestException::class);
        $parser->parse('articles', $request);
    }

    public function testCustomApplicationParameterIsRetainedAlongsideParsedCriteria(): void
    {
        $resources = new ResourceRegistry([Article::class]);
        $route = new \AlexFigures\JsonApi\Resource\Metadata\CustomRouteMetadata('articles.search', '/api/search', ['GET'], null, null, 'articles', [], [], null, 1);
        $routes = new \AlexFigures\JsonApi\Resource\Registry\CustomRouteRegistry([$route]);
        $repository = $this->createMock(\AlexFigures\JsonApi\Contract\Data\ResourceRepository::class);
        $repository->expects(self::never())->method('findOne');
        $parser = new QueryParser($resources, new PaginationConfig(), new SortingWhitelist($resources), new FilteringWhitelist($resources, $this->errors()), $this->errors(), new FilterParser());
        $factory = new \AlexFigures\JsonApi\CustomRoute\Context\CustomRouteContextFactory($routes, $resources, $repository, $parser, $this->errors());
        $request = Request::create('/api/search?q=application-text&page[size]=7');
        $context = $factory->create($request, 'articles.search');
        self::assertSame('application-text', $context->getQueryParam('q'));
        self::assertSame(7, $context->getCriteria()->pagination->size);
        $this->expectException(BadRequestException::class);
        $parser->parse('articles', $request);
    }

    public function testOpenApiExpandsInheritedWhitelistsAndExclusions(): void
    {
        $resources = new ResourceRegistry([Article::class, Author::class, Tag::class]);
        $metadata = $resources->getByType('articles');
        $metadata->filterableFields = new \AlexFigures\JsonApi\Resource\Attribute\FilterableFields([
            new \AlexFigures\JsonApi\Resource\Attribute\FilterableField('author', inherit: true, except: ['email']),
            new \AlexFigures\JsonApi\Resource\Attribute\FilterableField('tags', inherit: true),
        ]);
        $metadata->sortableFields = new \AlexFigures\JsonApi\Resource\Attribute\SortableFields([new \AlexFigures\JsonApi\Resource\Attribute\SortableField('author', inherit: true, except: ['email'])]);
        $spec = (new OpenApiSpecGenerator($resources, null, ['enabled' => true, 'route' => '/spec', 'title' => 'API', 'version' => '1', 'servers' => []], '/api', 'linkage'))->generate();
        $parameters = $spec['paths']['/api/articles']['get']['parameters'];
        $names = array_column($parameters, 'name');
        self::assertContains('filter[author.name][eq]', $names);
        self::assertContains('filter[tags.name][eq]', $names);
        self::assertNotContains('filter[author.email][eq]', $names);
        $sort = array_values(array_filter($parameters, static fn (array $parameter): bool => $parameter['name'] === 'sort'))[0];
        self::assertStringContainsString('author.name', $sort['description']);
        self::assertStringNotContainsString('author.email', $sort['description']);
    }

    public function testJsonSchemaDoesNotDependOnEnabledOpenApiAndRewritesReferences(): void
    {
        $resources = new ResourceRegistry([Article::class, Author::class, Tag::class]);
        $generator = new OpenApiSpecGenerator($resources, null, ['enabled' => false, 'route' => '/spec', 'title' => 'API', 'version' => '1', 'servers' => []], '/api', 'linkage');
        $response = (new \AlexFigures\JsonApi\Http\Controller\JsonSchemaController($generator, ['enabled' => true, 'include_profiles' => false]))();
        $schema = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('https://json-schema.org/draft/2020-12/schema', $schema['$schema']);
        self::assertArrayHasKey('ArticlesResource', $schema['$defs']);
        self::assertSame('#/$defs/ArticlesResource', $schema['$defs']['ArticlesResourceDocument']['properties']['data']['$ref']);
        self::assertArrayNotHasKey('x-jsonapi-profiles', $schema);
    }

    public function testSoftFlagsDoNotBypassOperandValidation(): void
    {
        $registry = new ResourceRegistry([Article::class]);
        $parser = new QueryParser($registry, new PaginationConfig(), new SortingWhitelist($registry), new FilteringWhitelist($registry, $this->errors()), $this->errors(), new FilterParser());
        $request = Request::create('/api/articles?filter[withTrashed][unexpected]=value');
        $profile = new SoftDeleteProfile();
        ProfileContext::store($request, new ProfileContext([$profile->uri() => $profile]));
        $this->expectException(BadRequestException::class);
        $parser->parse('articles', $request);
    }

    public function testJsonSchemaRouteAndNativeMediaPolicy(): void
    {
        $registry = new ResourceRegistry([]);
        $routes = (new JsonApiRouteLoader($registry, jsonSchemaConfig: ['enabled' => true, 'route' => '/schemas']))->load('.', 'jsonapi');
        $route = $routes->get('jsonapi.docs.schemas');
        self::assertNotNull($route);
        $request = Request::create('/schemas');
        $request->attributes->add($route->getDefaults());
        $policy = new ConfigMediaTypePolicyProvider(['default' => ['response' => ['default' => 'application/vnd.api+json']]], new ChannelScopeMatcher());
        self::assertSame(['application/schema+json', 'application/json'], $policy->getPolicy($request)->negotiableResponseTypes);
    }
}
