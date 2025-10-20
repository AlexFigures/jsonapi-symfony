<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Symfony\Negotiation;

use AlexFigures\Symfony\Bridge\Symfony\Routing\Attribute\MediaChannel;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;

final class ChannelScopeMatcher
{
    /**
     * @param array{path_prefix?: string|null, route_name?: string|null, attribute?: string|null} $scope
     */
    public function matches(Request $request, array $scope): bool
    {
        if ($scope === []) {
            return true;
        }

        if (($scope['path_prefix'] ?? null) !== null) {
            $path = $request->getPathInfo();
            if (!$this->matchPattern($scope['path_prefix'], $path)) {
                return false;
            }
        }

        if (($scope['route_name'] ?? null) !== null) {
            $rawRoute = $request->attributes->get('_route');
            if (!is_string($rawRoute) || $rawRoute === '') {
                return false;
            }

            if (!$this->matchPattern($scope['route_name'], $rawRoute)) {
                return false;
            }
        }

        if (($scope['attribute'] ?? null) !== null) {
            $channel = $request->attributes->get(MediaChannel::REQUEST_ATTRIBUTE);
            if (!is_string($channel) || $channel === '') {
                return false;
            }

            if (!$this->matchPattern($scope['attribute'], $channel)) {
                return false;
            }
        }

        return true;
    }

    private function matchPattern(string $pattern, string $value): bool
    {
        $regex = $this->wrapPattern($pattern);
        $result = @preg_match($regex, $value);

        if ($result === false) {
            throw new InvalidArgumentException(sprintf('Invalid regular expression "%s".', $pattern));
        }

        return $result === 1;
    }

    private function wrapPattern(string $pattern): string
    {
        $delimiter = '~';

        return $delimiter . $pattern . $delimiter . 'u';
    }
}
