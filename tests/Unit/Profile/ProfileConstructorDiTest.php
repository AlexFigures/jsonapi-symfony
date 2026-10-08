<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Unit\Profile;

use AlexFigures\JsonApi\Bridge\Symfony\DependencyInjection\Compiler\ValidateProfilesPass;
use AlexFigures\JsonApi\Profile\Descriptor\ProfileDescriptor;
use AlexFigures\JsonApi\Profile\ProfileInterface;
use AlexFigures\JsonApi\Profile\ProfileRegistry;
use AlexFigures\JsonApi\Profile\Validation\FieldRequirement;
use AlexFigures\JsonApi\Profile\Validation\ProfileRequirements;
use AlexFigures\JsonApi\Tests\Util\FakeProfile;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class ProfileConstructorDiTest extends TestCase
{
    public function testProfileWarningsAreCompilerDiagnosticsRatherThanDeprecations(): void
    {
        $resource = new class () {
            public ?string $value = null;
        };
        $profile = new class () implements ProfileInterface {
            public function uri(): string
            {
                return 'urn:test:warning';
            }

            public function descriptor(): ProfileDescriptor
            {
                return new ProfileDescriptor($this->uri(), 'Warning profile', '1.0');
            }

            public function hooks(): iterable
            {
                return [];
            }

            public function requirements(): ?ProfileRequirements
            {
                return new ProfileRequirements(fields: ['value' => new FieldRequirement('string')]);
            }
        };
        $container = new ContainerBuilder();
        $container->setParameter('jsonapi.profiles.enabled_by_default', [$profile->uri()]);
        $container->setParameter('jsonapi.discovered_resources', ['models' => $resource::class]);
        $container->register('warning-profile', $profile::class)->addTag('jsonapi.profile');

        set_error_handler(static function (int $severity, string $message): bool {
            if ($severity === \E_USER_DEPRECATED) {
                throw new \ErrorException($message, 0, $severity);
            }

            return false;
        });
        try {
            (new ValidateProfilesPass())->process($container);
        } finally {
            restore_error_handler();
        }

        self::assertStringContainsString("Field 'value' is nullable", implode("\n", $container->getCompiler()->getLog()));
    }

    public function testConstructorInjectedProfileCompilesAndIsValidatedAfterConstruction(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('jsonapi.profiles.enabled_by_default', ['https://example.test/di']);
        $container->setParameter('jsonapi.discovered_resources', ['models' => \stdClass::class]);
        $container->register(\AlexFigures\JsonApi\Tests\Unit\Profile\Fixtures\InjectedProfileContext::class)->setArguments(['https://example.test/di']);
        $container->register(\AlexFigures\JsonApi\Tests\Unit\Profile\Fixtures\InjectedProfile::class)->setArguments([new Reference(\AlexFigures\JsonApi\Tests\Unit\Profile\Fixtures\InjectedProfileContext::class)])->addTag('jsonapi.profile');
        $container->register(ProfileRegistry::class)->setPublic(true)->setArguments([[new Reference(\AlexFigures\JsonApi\Tests\Unit\Profile\Fixtures\InjectedProfile::class)], ['models' => \stdClass::class], ['https://example.test/di']]);
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
        $profile = new \AlexFigures\JsonApi\Tests\Unit\Profile\Fixtures\InjectedProfile(new \AlexFigures\JsonApi\Tests\Unit\Profile\Fixtures\InjectedProfileContext('https://example.test/di', true));
        new ProfileRegistry([$profile], ['models' => \stdClass::class], [$profile->uri()]);
    }

    public function testDeferredProfilesDoNotDisableUnknownUriValidationAtRuntime(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('not found in profile registry');
        new ProfileRegistry([new FakeProfile('https://example.test/di')], ['models' => \stdClass::class], ['https://example.test/missing']);
    }
}
