<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Negotiation;

/** @api */
final readonly class MediaTypePolicy
{
    /**
     * @param list<string> $allowedRequestTypes
     * @param list<string> $negotiableResponseTypes
     */
    public function __construct(
        public array $allowedRequestTypes,
        public array $negotiableResponseTypes,
        public string $defaultResponseType,
        public bool $enforceJsonApiParameters,
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
