<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Tests\Unit\Bridge\Symfony\DependencyInjection;

use JsonApi\Symfony\Bridge\Symfony\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
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

    private function process(array $configs = []): array
    {
        $processor = new Processor();
        $configuration = new Configuration();

        return $processor->processConfiguration($configuration, $configs);
    }
}
