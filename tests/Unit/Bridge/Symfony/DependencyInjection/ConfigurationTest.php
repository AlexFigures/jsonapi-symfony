<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Bridge\Symfony\DependencyInjection;

use AlexFigures\Symfony\Bridge\Symfony\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
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

    public function testInactiveOptionsEmitExplicitDeprecationsOnlyWhenConfigured(): void
    {
        $messages = [];
        set_error_handler(static function (int $severity, string $message) use (&$messages): bool {
            if ($severity === \E_USER_DEPRECATED) {
                $messages[] = $message;
                return true;
            }
            return false;
        });
        try {
            (new Processor())->processConfiguration(new Configuration(), [[]]);
            self::assertSame([], $messages);
            (new Processor())->processConfiguration(new Configuration(), [[
                'dx' => ['dev_toolbar' => true],
                'errors' => ['locale' => 'ru'],
                'performance' => ['doctrine' => ['enable_query_cache' => false, 'query_cache_pool' => 'app.cache', 'enable_second_level_cache' => true, 'hydrate_partial_by_fields' => false, 'default_fetch' => 'eager']],
            ]]);
            self::assertCount(7, $messages);
            foreach ($messages as $message) {
                self::assertStringContainsString('has no runtime implementation', $message);
            }
        } finally {
            restore_error_handler();
        }
    }

    public function testDxSectionDefaults(): void
    {
        $config = $this->process();

        self::assertSame(
            [
                'dev_toolbar' => true,
                'sandbox' => [
                    'enabled' => true,
                    'route' => '/_jsonapi/sandbox',
                ],
                'doctor' => [
                    'enabled' => true,
                    'rules' => [
                        'negotiation.vary.accept',
                        'errors.listener.registered',
                        'profiles.per_type.known',
                        'filters.whitelist.coverage',
                        'pagination.cursor.sort_key.stable',
                    ],
                ],
                'maker' => [
                    'defaults' => [
                        'namespace' => 'App\\JsonApi',
                        'resource_type_prefix' => '',
                    ],
                ],
            ],
            $config['dx']
        );
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

    public function testReleaseDefaults(): void
    {
        $config = $this->process();

        self::assertSame(
            [
                'semver' => 'strict',
                'bc_policy' => 'minor-no-break',
                'min_php' => '8.2',
                'min_symfony' => '7.1',
            ],
            $config['release']
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
