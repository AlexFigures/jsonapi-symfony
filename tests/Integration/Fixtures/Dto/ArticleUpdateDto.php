<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Integration\Fixtures\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for updating Article resources.
 *
 * All fields are optional to support partial updates (PATCH).
 */
final readonly class ArticleUpdateDto
{
    public function __construct(
        #[Assert\Length(min: 3, max: 255)]
        public ?string $title = null,
        #[Assert\Length(min: 10)]
        public ?string $content = null,
    ) {
    }
}
