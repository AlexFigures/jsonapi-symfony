<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Symfony\Negotiation;

use AlexFigures\Symfony\Http\Negotiation\MediaType;
use AlexFigures\Symfony\Http\Negotiation\MediaTypePolicy;
use AlexFigures\Symfony\Http\Negotiation\MediaTypePolicyProviderInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal The default policy provider used by the Symfony bridge.
 */
final class ConfigMediaTypePolicyProvider implements MediaTypePolicyProviderInterface
{
    /**
     * @var array<int, array{scope: array<string, string|null>, policy: MediaTypePolicy}>
     */
    private array $channels;

    /**
     * @param array{
     *     default: array{request?: array{allowed?: list<string>}, response: array{default: string, negotiable?: list<string>}},
     *     channels?: array<int, array{scope?: array<string, string|null>, request?: array{allowed?: list<string>}, response?: array{default?: string, negotiable?: list<string>}}>
     * } $config
     */
    public function __construct(
        array $config,
        private readonly ChannelScopeMatcher $matcher,
    ) {
        $this->channels = $this->buildChannels($config['channels'] ?? []);
        $this->defaultPolicy = $this->buildPolicy($config['default']);
    }

    public function getPolicy(Request $request): MediaTypePolicy
    {
        foreach ($this->channels as $channel) {
            if ($this->matcher->matches($request, $channel['scope'])) {
                return $channel['policy'];
            }
        }

        return $this->defaultPolicy;
    }

    /**
     * @param array<int, array{scope?: array<string, string|null>, request?: array{allowed?: list<string>}, response?: array{default?: string, negotiable?: list<string>}}> $channels
     * @return array<int, array{scope: array<string, string|null>, policy: MediaTypePolicy}>
     */
    private function buildChannels(array $channels): array
    {
        $result = [];
        foreach ($channels as $channelConfig) {
            $scope = $channelConfig['scope'] ?? [];
            $policyConfig = [
                'request' => $channelConfig['request'] ?? ['allowed' => ['*']],
                'response' => ($channelConfig['response'] ?? []) + ['default' => MediaType::JSON_API],
            ];

            $result[] = [
                'scope' => [
                    'path_prefix' => $scope['path_prefix'] ?? null,
                    'route_name' => $scope['route_name'] ?? null,
                    'attribute' => $scope['attribute'] ?? null,
                ],
                'policy' => $this->buildPolicy($policyConfig),
            ];
        }

        return $result;
    }

    /**
     * @param array{request?: array{allowed?: list<string>}, response: array{default: string, negotiable?: list<string>}} $config
     */
    private function buildPolicy(array $config): MediaTypePolicy
    {
        $allowed = $this->normalizeList($config['request']['allowed'] ?? ['*']);
        $negotiable = $this->normalizeList($config['response']['negotiable'] ?? []);
        $default = $config['response']['default'];

        if ($negotiable === []) {
            $negotiable = [$this->normalizeType($default)];
        } else {
            $normalizedDefault = $this->normalizeType($default);
            if (!in_array($normalizedDefault, $negotiable, true)) {
                array_unshift($negotiable, $normalizedDefault);
            }
        }

        return new MediaTypePolicy(
            $allowed,
            $negotiable,
            $default,
            $this->shouldEnforceJsonApiParameters($allowed),
        );
    }

    /**
     * @param list<string> $allowed
     */
    private function shouldEnforceJsonApiParameters(array $allowed): bool
    {
        if (count($allowed) !== 1) {
            return false;
        }

        $mediaType = $allowed[0];

        return $mediaType === MediaType::JSON_API;
    }

    private MediaTypePolicy $defaultPolicy;

    /**
     * @param list<string> $types
     * @return list<string>
     */
    private function normalizeList(array $types): array
    {
        $normalized = [];
        foreach ($types as $type) {
            $normalized[] = $this->normalizeType($type);
        }

        return $normalized;
    }

    private function normalizeType(string $type): string
    {
        $trimmed = trim(strtolower($type));

        if ($trimmed === '*') {
            return '*';
        }

        return $trimmed;
    }
}
