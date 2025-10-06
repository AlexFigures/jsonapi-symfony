<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Http\Document;

use JsonApi\Symfony\Contract\Data\Slice;
use JsonApi\Symfony\Http\Document\DocumentBuilder;
use JsonApi\Symfony\Http\Link\LinkGenerator;
use JsonApi\Symfony\Profile\ProfileContext;
use JsonApi\Symfony\Profile\Hook\DocumentHook;
use JsonApi\Symfony\Query\Criteria;
use JsonApi\Symfony\Resource\Metadata\AttributeMetadata;
use JsonApi\Symfony\Resource\Metadata\RelationshipMetadata;
use JsonApi\Symfony\Resource\Metadata\ResourceMetadata;
use JsonApi\Symfony\Resource\Registry\ResourceRegistryInterface;
use JsonApi\Symfony\Tests\Util\FakeProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

#[CoversClass(DocumentBuilder::class)]
final class DocumentBuilderTest extends TestCase
{
    public function testDocumentHooksAugmentDocuments(): void
    {
        $articleMetadata = new ResourceMetadata(
            'articles',
            Article::class,
            [
                'title' => new AttributeMetadata('title'),
            ],
            [
                'comments' => new RelationshipMetadata('comments', true, 'comments'),
            ],
        );

        $commentMetadata = new ResourceMetadata(
            'comments',
            Comment::class,
            [
                'body' => new AttributeMetadata('body'),
            ],
            []
        );

        $registry = new class ($articleMetadata, $commentMetadata) implements ResourceRegistryInterface {
            public function __construct(private ResourceMetadata $article, private ResourceMetadata $comment)
            {
            }

            public function getByType(string $type): ResourceMetadata
            {
                return match ($type) {
                    'articles' => $this->article,
                    'comments' => $this->comment,
                    default => throw new \RuntimeException('Unknown type: ' . $type),
                };
            }

            public function hasType(string $type): bool
            {
                return in_array($type, ['articles', 'comments'], true);
            }

            public function getByClass(string $class): ?ResourceMetadata
            {
                return match ($class) {
                    Article::class => $this->article,
                    Comment::class => $this->comment,
                    default => null,
                };
            }
        };

        $urls = new class () implements UrlGeneratorInterface {
            private RequestContext $context;

            public function __construct()
            {
                $this->context = new RequestContext();
            }

            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                ksort($parameters);

                return $name . ':' . http_build_query($parameters);
            }

            public function setContext(RequestContext $context): void
            {
                $this->context = $context;
            }

            public function getContext(): RequestContext
            {
                return $this->context;
            }
        };

        $builder = new DocumentBuilder(
            $registry,
            PropertyAccess::createPropertyAccessor(),
            new LinkGenerator($urls),
            'when_included'
        );

        $hook = new class () implements DocumentHook {
            public function onTopLevelLinks(ProfileContext $context, array &$links, Request $request): void
            {
                $links['profiles'] = $context->activeUris();
            }

            public function onResourceRelationships(ProfileContext $context, ResourceMetadata $metadata, array &$relationshipsPayload, object $model): void
            {
                foreach (array_keys($relationshipsPayload) as $name) {
                    $relationshipsPayload[$name]['meta']['profiles'] = $context->activeUris();
                }
            }

            public function onTopLevelMeta(ProfileContext $context, array &$meta): void
            {
                $meta['profiles'] = $context->activeUris();
            }
        };

        $profile = new FakeProfile('https://profiles.test/a', [$hook]);
        $context = new ProfileContext([
            $profile->uri() => $profile,
        ]);

        $article = new Article('1', 'Hello World', [new Comment('10', 'First!')]);
        $criteria = new Criteria();
        $criteria->include = ['comments'];
        $slice = new Slice([$article], 1, 10, 1);

        $request = Request::create('https://api.test/articles');
        ProfileContext::store($request, $context);

        $collectionDocument = $builder->buildCollection('articles', [$article], $criteria, $slice, $request);

        self::assertSame('https://api.test/articles', $collectionDocument['links']['self']);
        self::assertSame(['https://profiles.test/a'], $collectionDocument['links']['profiles']);
        self::assertSame(['https://profiles.test/a'], $collectionDocument['meta']['profiles']);
        self::assertSame(['https://profiles.test/a'], $collectionDocument['data'][0]['relationships']['comments']['meta']['profiles']);
        self::assertSame('jsonapi.related:id=1&rel=comments&type=articles', $collectionDocument['data'][0]['relationships']['comments']['links']['related']);
        self::assertSame('jsonapi.resource:id=10&type=comments', $collectionDocument['included'][0]['links']['self']);

        $resourceRequest = Request::create('https://api.test/articles/1');
        ProfileContext::store($resourceRequest, $context);

        $resourceDocument = $builder->buildResource('articles', $article, $criteria, $resourceRequest);

        self::assertSame('jsonapi.resource:id=1&type=articles', $resourceDocument['data']['links']['self']);
        self::assertSame(['https://profiles.test/a'], $resourceDocument['links']['profiles']);
        self::assertSame(['https://profiles.test/a'], $resourceDocument['meta']['profiles']);
        self::assertSame(['https://profiles.test/a'], $resourceDocument['data']['relationships']['comments']['meta']['profiles']);
    }
}

final class Article
{
    /** @param list<Comment> $comments */
    public function __construct(
        public string $id,
        public string $title,
        public array $comments,
    ) {
    }
}

final class Comment
{
    public function __construct(
        public string $id,
        public string $body,
    ) {
    }
}
