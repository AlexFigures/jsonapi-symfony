<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Unit\Bridge\Symfony\DependencyInjection;

use AlexFigures\JsonApi\Bridge\Symfony\DependencyInjection\Configuration;
use AlexFigures\JsonApi\Bridge\Symfony\DependencyInjection\JsonApiExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ConfigurationTest extends TestCase
{
    public function testLegacyMediaPolicyEmitsOneDeprecationAndRemainsEffective(): void
    {
        $deprecations = [];
        set_error_handler(static function (int $severity, string $message) use (&$deprecations): bool {
            if ($severity !== \E_USER_DEPRECATED) {
                return false;
            }
            $deprecations[] = $message;

            return true;
        });
        $container = new ContainerBuilder();
        try {
            (new JsonApiExtension())->load([['media_type' => 'application/json', 'data_layer' => ['provider' => 'custom']]], $container);
        } finally {
            restore_error_handler();
        }

        self::assertCount(1, $deprecations);
        self::assertStringContainsString('alexfigures/symfony-jsonapi-bundle', $deprecations[0]);
        $media = $container->getParameter('jsonapi.media_types');
        self::assertSame(['application/json'], $media['default']['request']['allowed']);
        self::assertSame('application/json', $media['default']['response']['default']);
    }

    public function testResourceTypeKeysKeepTheirPublicKebabCaseSpelling(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'profiles' => ['per_type' => ['feature-memos' => ['urn:jsonapi:profile:audit-trail']]],
            'write' => ['client_generated_ids' => ['feature-memos' => true]],
            'cache' => ['last_modified' => ['per_type' => ['feature-memos' => 'modifiedAt']]],
        ]]);
        self::assertSame(['feature-memos' => ['urn:jsonapi:profile:audit-trail']], $config['profiles']['per_type']);
        self::assertSame(['feature-memos' => true], $config['write']['client_generated_ids']);
        self::assertSame(['feature-memos' => 'modifiedAt'], $config['cache']['last_modified']['per_type']);
    }

    public function testFilterLimitsHaveSafeDefaultsAndCanBeDisabled(): void
    {
        $limits = $this->process()['limits'];
        self::assertSame(8, $limits['filter_max_depth']);
        self::assertSame(100, $limits['filter_max_nodes']);
        self::assertSame(200, $limits['filter_max_operands']);
        $config = (new Processor())->processConfiguration(new Configuration(), [['limits' => ['filter_max_depth' => 0, 'filter_max_nodes' => 0, 'filter_max_operands' => 0]]]);
        self::assertSame(0, $config['limits']['filter_max_depth']);
        self::assertSame(0, $config['limits']['filter_max_nodes']);
        self::assertSame(0, $config['limits']['filter_max_operands']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidFilterLimits')]
    public function testNegativeFilterLimitIsInvalid(string $name): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);
        (new Processor())->processConfiguration(new Configuration(), [['limits' => [$name => -1]]]);
    }

    public static function invalidFilterLimits(): iterable
    {
        foreach (['filter_max_depth', 'filter_max_nodes', 'filter_max_operands'] as $name) {
            yield $name => [$name];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('removedOptions')]
    public function testRemovedOptionsFailConfiguration(array $input, string $path, string $name): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unrecognized option "' . $name . '" under "' . $path . '"');
        $this->process([$input]);
    }

    public static function removedOptions(): iterable
    {
        yield 'release section' => [['release' => ['semver' => 'strict']], 'jsonapi', 'release'];
        yield 'dx section' => [['dx' => ['dev_toolbar' => true]], 'jsonapi', 'dx'];
        yield 'error locale' => [['errors' => ['locale' => 'ru']], 'jsonapi.errors', 'locale'];
        foreach (['enable_query_cache' => false, 'query_cache_pool' => 'cache.app', 'enable_second_level_cache' => true, 'hydrate_partial_by_fields' => false, 'default_fetch' => 'eager'] as $name => $value) {
            yield $name => [['performance' => ['doctrine' => [$name => $value]]], 'jsonapi.performance.doctrine', $name];
        }
    }

    public function testRemovedOptionsAreAbsentFromDefaults(): void
    {
        $config = $this->process();
        self::assertArrayNotHasKey('dx', $config);
        self::assertArrayNotHasKey('locale', $config['errors']);
        self::assertSame(['collection_sort_policy' => 'legacy'], $config['performance']['doctrine']);
        self::assertTrue($config['performance']['head_enabled']);
    }

    public function testDocsSectionDefaults(): void
    {
        $config = $this->process();

        self::assertSame(
            [
                'generator' => [
                    'openapi' => [
                        'enabled' => true,
                        'route' => '/_jsonapi/openapi.json',
                        'title' => 'My API',
                        'version' => '1.0.0',
                        'servers' => ['https://api.example.com'],
                    ],
                    'json_schema' => [
                        'enabled' => true,
                        'route' => '/_jsonapi/schemas',
                        'include_profiles' => true,
                    ],
                ],
                'ui' => [
                    'enabled' => true,
                    'route' => '/_jsonapi/docs',
                    'spec_url' => '/_jsonapi/openapi.json',
                    'theme' => 'swagger',
                ],
            ],
            $config['docs']
        );
    }

    public function testReadSafetyDefaultsAndStrictCollectionSortPolicy(): void
    {
        $config = $this->process();
        self::assertSame(10000, $config['limits']['relationship_max_identifiers']);
        self::assertSame('legacy', $config['performance']['doctrine']['collection_sort_policy']);
        $strict = $this->process([['limits' => ['relationship_max_identifiers' => 0], 'performance' => ['doctrine' => ['collection_sort_policy' => 'reject']]]]);
        self::assertSame(0, $strict['limits']['relationship_max_identifiers']);
        self::assertSame('reject', $strict['performance']['doctrine']['collection_sort_policy']);
    }

    public function testNegativeRelationshipBudgetIsRejected(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);
        $this->process([['limits' => ['relationship_max_identifiers' => -1]]]);
    }

    public function testUnknownCollectionSortPolicyIsRejected(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);
        $this->process([['performance' => ['doctrine' => ['collection_sort_policy' => 'accidental']]]]);
    }

    private function process(array $configs = []): array
    {
        $processor = new Processor();
        $configuration = new Configuration();

        return $processor->processConfiguration($configuration, $configs);
    }
}
