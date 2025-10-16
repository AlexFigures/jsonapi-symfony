<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Negotiation;

final class MediaTypePolicy
{
    /**
     * @param list<string> $allowedRequestTypes
     * @param list<string> $negotiableResponseTypes
     */
    public function __construct(
        public readonly array $allowedRequestTypes,
        public readonly array $negotiableResponseTypes,
        public readonly string $defaultResponseType,
        public readonly bool $enforceJsonApiParameters,
    ) {
    }

    public function allowsAnyRequestType(): bool
    {
        return $this->allowedRequestTypes === ['*'];
    }

    public function allowsAnyResponseType(): bool
    {
        return $this->negotiableResponseTypes === ['*'];
    }
}
