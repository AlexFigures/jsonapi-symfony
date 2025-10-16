<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Unit\Bridge\Symfony\Negotiation;

use AlexFigures\Symfony\Bridge\Symfony\Negotiation\ChannelScopeMatcher;
use AlexFigures\Symfony\Bridge\Symfony\Negotiation\ConfigMediaTypePolicyProvider;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ConfigMediaTypePolicyProviderTest extends TestCase
{
    public function testReturnsDefaultPolicyWhenNoChannelMatches(): void
    {
        $provider = new ConfigMediaTypePolicyProvider($this->config(), new ChannelScopeMatcher());
        $policy = $provider->getPolicy(Request::create('/api/articles'));

        self::assertSame([MediaType::JSON_API], $policy->allowedRequestTypes);
        self::assertSame([MediaType::JSON_API], $policy->negotiableResponseTypes);
        self::assertSame(MediaType::JSON_API, $policy->defaultResponseType);
        self::assertTrue($policy->enforceJsonApiParameters);
    }

    public function testMatchesChannelByPath(): void
    {
        $provider = new ConfigMediaTypePolicyProvider(
            [
                'default' => $this->config()['default'],
                'channels' => [
                    'sandbox' => [
                        'scope' => ['path_prefix' => '^/sandbox'],
                        'request' => ['allowed' => ['multipart/form-data']],
                        'response' => [
                            'default' => 'application/json',
                            'negotiable' => ['application/json', 'text/html'],
                        ],
                    ],
                ],
            ],
            new ChannelScopeMatcher()
        );

        $policy = $provider->getPolicy(Request::create('/sandbox/upload'));

        self::assertSame(['multipart/form-data'], $policy->allowedRequestTypes);
        self::assertSame(['application/json', 'text/html'], $policy->negotiableResponseTypes);
        self::assertSame('application/json', $policy->defaultResponseType);
        self::assertFalse($policy->enforceJsonApiParameters);
    }

    public function testNegotiableDefaultsToResponseDefault(): void
    {
        $provider = new ConfigMediaTypePolicyProvider(
            [
                'default' => [
                    'request' => ['allowed' => ['application/json']],
                    'response' => ['default' => 'application/json', 'negotiable' => []],
                ],
                'channels' => [],
            ],
            new ChannelScopeMatcher()
        );

        $policy = $provider->getPolicy(Request::create('/any'));

        self::assertSame(['application/json'], $policy->negotiableResponseTypes);
    }

    public function testWildcardAllowsAnyRequest(): void
    {
        $provider = new ConfigMediaTypePolicyProvider(
            [
                'default' => [
                    'request' => ['allowed' => ['*']],
                    'response' => ['default' => MediaType::JSON_API, 'negotiable' => []],
                ],
                'channels' => [],
            ],
            new ChannelScopeMatcher()
        );

        $policy = $provider->getPolicy(Request::create('/'));

        self::assertTrue($policy->allowsAnyRequestType());
        self::assertFalse($policy->enforceJsonApiParameters);
    }

    /**
     * @return array{default: array{request: array{allowed: list<string>}, response: array{default: string, negotiable: list<string>}}}
     */
    private function config(): array
    {
        return [
            'default' => [
                'request' => ['allowed' => [MediaType::JSON_API]],
                'response' => [
                    'default' => MediaType::JSON_API,
                    'negotiable' => [],
                ],
            ],
        ];
    }
}
