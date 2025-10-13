<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Profile;

use AlexFigures\Symfony\Contract\Data\ChangeSet;
use AlexFigures\Symfony\Contract\Data\ResourceIdentifier;
use AlexFigures\Symfony\Profile\Hook\DocumentHook;
use AlexFigures\Symfony\Profile\Hook\QueryHook;
use AlexFigures\Symfony\Profile\Hook\ReadHook;
use AlexFigures\Symfony\Profile\Hook\RelationshipHook;
use AlexFigures\Symfony\Profile\Hook\WriteHook;
use AlexFigures\Symfony\Profile\ProfileContext;
use AlexFigures\Symfony\Query\Criteria;
use AlexFigures\Symfony\Tests\Util\FakeProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(ProfileContext::class)]
final class ProfileContextTest extends TestCase
{
    public function testContextExposesProfilesHooksAndSources(): void
    {
        $documentHook = new class () implements DocumentHook {
            public function onTopLevelLinks(ProfileContext $context, array &$links, Request $request): void
            {
            }

            public function onResourceRelationships(ProfileContext $context, \AlexFigures\Symfony\Resource\Metadata\ResourceMetadata $metadata, array &$relationshipsPayload, object $model): void
            {
            }

            public function onTopLevelMeta(ProfileContext $context, array &$meta): void
            {
            }
        };

        $queryHook = new class () implements QueryHook {
            public function onParseQuery(ProfileContext $context, Request $request, Criteria $criteria): void
            {
            }
        };

        $readHook = new class () implements ReadHook {
            public function onBeforeFindCollection(ProfileContext $context, string $type, Criteria $criteria): void
            {
            }

            public function onBeforeFindOne(ProfileContext $context, string $type, string $id, Criteria $criteria): void
            {
            }
        };

        $writeHook = new class () implements WriteHook {
            public function onBeforeCreate(ProfileContext $context, string $type, ChangeSet $changeSet): void
            {
            }

            public function onBeforeUpdate(ProfileContext $context, string $type, string $id, ChangeSet $changeSet): void
            {
            }

            public function onBeforeDelete(ProfileContext $context, string $type, string $id): void
            {
            }
        };

        $relationshipHook = new class () implements RelationshipHook {
            public function onBeforeRelReplaceToMany(ProfileContext $context, string $type, string $id, string $relationship, array $targets): void
            {
            }

            public function onBeforeRelReplaceToOne(ProfileContext $context, string $type, string $id, string $relationship, ?ResourceIdentifier $target): void
            {
            }

            public function onBeforeRelAddToMany(ProfileContext $context, string $type, string $id, string $relationship, array $targets): void
            {
            }

            public function onBeforeRelRemoveFromToMany(ProfileContext $context, string $type, string $id, string $relationship, array $targets): void
            {
            }
        };

        $profileA = new FakeProfile('https://profiles.test/a', [$documentHook, $queryHook, $readHook, $writeHook, $relationshipHook]);
        $profileB = new FakeProfile('https://profiles.test/b');
        $profileC = new FakeProfile('https://profiles.test/c');
        $context = new ProfileContext(
            [
                $profileA->uri() => $profileA,
                $profileB->uri() => $profileB,
            ],
            [
                'articles' => [$profileB, $profileC],
            ],
            [
                'default' => [$profileA->uri()],
            ]
        );

        self::assertSame([
            $profileA->uri(),
            $profileB->uri(),
        ], $context->activeUris());

        self::assertTrue($context->has($profileA->uri()));
        self::assertSame($profileA, $context->profile($profileA->uri()));
        self::assertNull($context->profile('https://profiles.test/unknown'));

        self::assertSame([$profileA, $profileB], $context->profiles());
        self::assertSame([$profileA, $profileB, $profileC], $context->profilesForType('articles'));
        self::assertSame([$profileA, $profileB], $context->profilesForType('unknown'));

        self::assertSame([
            'default' => [$profileA->uri()],
        ], $context->sources());

        self::assertSame([$documentHook], $context->documentHooks());
        self::assertSame([$queryHook], $context->queryHooks());
        self::assertSame([$readHook], $context->readHooks());
        self::assertSame([$writeHook], $context->writeHooks());
        self::assertSame([$relationshipHook], $context->relationshipHooks());

        $request = Request::create('/articles');
        ProfileContext::store($request, $context);
        self::assertSame($context, ProfileContext::fromRequest($request));
    }
}
