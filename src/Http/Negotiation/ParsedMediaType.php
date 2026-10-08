<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Negotiation;

use Symfony\Component\HttpFoundation\HeaderUtils;

/** @internal */
final readonly class ParsedMediaType
{
    /**
     * @param array<string, string|bool> $parameters
     * @param array<string, string|bool> $acceptParameters
     */
    private function __construct(
        public string $name,
        public array $parameters,
        public float $quality,
        public array $acceptParameters = [],
    ) {
    }

    /** @return list<self> */
    public static function parse(string $header, bool $accept = false): array
    {
        $result = [];
        foreach (HeaderUtils::split($header, ',;=') as $parts) {
            $name = strtolower(trim(array_shift($parts)[0] ?? ''));
            $parameters = [];
            $quality = 1.0;
            $afterQuality = false;
            $acceptParameters = [];
            foreach ($parts as $part) {
                $key = strtolower($part[0]);
                $value = $part[1] ?? true;
                if ($accept && $key === 'q') {
                    $afterQuality = true;
                    $quality = is_string($value) && preg_match('/^0(?:\.[0-9]{0,3})?$|^1(?:\.0{0,3})?$/D', $value) ? (float) $value : 0.0;
                    continue;
                }
                if ($afterQuality) {
                    $acceptParameters[$key] = $value;
                } else {
                    $parameters[$key] = $value;
                }
            }
            $result[] = new self($name, $parameters, $quality, $acceptParameters);
        }
        usort($result, static fn (self $a, self $b): int => $b->quality <=> $a->quality);
        return $result;
    }

    /** @return list<string> */
    public function extensions(): array
    {
        $value = $this->parameters['ext'] ?? '';
        return is_string($value) && trim($value) !== '' ? preg_split('/\s+/', trim($value)) ?: [] : [];
    }

    /** @param list<string> $extensions */
    public function validJsonApi(array $extensions = []): bool
    {
        if ($this->name !== MediaType::JSON_API || array_diff(array_keys($this->parameters), ['ext', 'profile']) !== []) {
            return false;
        }
        foreach ($this->parameters as $value) {
            if (!is_string($value) || trim($value) === '') {
                return false;
            }
        }
        return array_diff($this->extensions(), $extensions) === [];
    }
}
