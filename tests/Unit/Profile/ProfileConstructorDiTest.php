<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Profile;

use AlexFigures\Symfony\Bridge\Symfony\DependencyInjection\Compiler\ValidateProfilesPass;
use AlexFigures\Symfony\Profile\ProfileRegistry;
use AlexFigures\Symfony\Tests\Util\FakeProfile;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class ProfileConstructorDiTest extends TestCase
{
    public function testConstructorInjectedProfileCompilesAndIsValidatedAfterConstruction(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('jsonapi.profiles.enabled_by_default', ['https://example.test/di']);
        $container->setParameter('jsonapi.discovered_resources', ['models' => \stdClass::class]);
        $container->register(\AlexFigures\Symfony\Tests\Unit\Profile\Fixtures\InjectedProfileContext::class)->setArguments(['https://example.test/di']);
        $container->register(\AlexFigures\Symfony\Tests\Unit\Profile\Fixtures\InjectedProfile::class)->setArguments([new Reference(\AlexFigures\Symfony\Tests\Unit\Profile\Fixtures\InjectedProfileContext::class)])->addTag('jsonapi.profile');
        $container->register(ProfileRegistry::class)->setPublic(true)->setArguments([[new Reference(\AlexFigures\Symfony\Tests\Unit\Profile\Fixtures\InjectedProfile::class)], ['models' => \stdClass::class], ['https://example.test/di']]);
        $container->addCompilerPass(new ValidateProfilesPass());
        $container->compile();
        $registry = $container->get(ProfileRegistry::class);
        self::assertInstanceOf(ProfileRegistry::class, $registry);
        self::assertTrue($registry->has('https://example.test/di'));
    }

    public function testRequirementsOfInjectedProfilesStillFailDeterministically(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Missing required field');
        $profile = new \AlexFigures\Symfony\Tests\Unit\Profile\Fixtures\InjectedProfile(new \AlexFigures\Symfony\Tests\Unit\Profile\Fixtures\InjectedProfileContext('https://example.test/di', true));
        new ProfileRegistry([$profile], ['models' => \stdClass::class], [$profile->uri()]);
    }

    public function testDeferredProfilesDoNotDisableUnknownUriValidationAtRuntime(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('not found in profile registry');
        new ProfileRegistry([new FakeProfile('https://example.test/di')], ['models' => \stdClass::class], ['https://example.test/missing']);
    }
}
