<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Profile;

use AlexFigures\Symfony\Profile\Descriptor\ProfileDescriptor;
use AlexFigures\Symfony\Profile\ProfileRegistry;
use AlexFigures\Symfony\Tests\Util\FakeProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProfileRegistry::class)]
final class ProfileRegistryTest extends TestCase
{
    public function testRegistryStoresProfilesAndDescriptors(): void
    {
        $descriptor = new ProfileDescriptor('https://profiles.test/a', 'Fake', '1.0');
        $profile = new FakeProfile('https://profiles.test/a', descriptor: $descriptor);

        $registry = new ProfileRegistry([$profile]);
        self::assertTrue($registry->has($profile->uri()));
        self::assertSame($profile, $registry->get($profile->uri()));

        $all = $registry->all();
        self::assertSame([$profile->uri() => $profile], $all);

        $descriptors = $registry->descriptors();
        self::assertSame([$profile->uri() => $descriptor], $descriptors);

        $another = new FakeProfile('https://profiles.test/b');
        $registry->register($another);

        self::assertTrue($registry->has($another->uri()));
        self::assertSame($another, $registry->get($another->uri()));
        self::assertArrayHasKey($another->uri(), $registry->descriptors());
    }
}
