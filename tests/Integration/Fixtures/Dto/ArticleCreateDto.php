<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Fixtures\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for creating Article resources.
 * 
 * This DTO validates incoming JSON:API payloads before
 * mapping them to the Article Entity.
 */
final class ArticleCreateDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Title is required')]
        #[Assert\Length(min: 3, max: 255)]
        public readonly string $title,
        
        #[Assert\NotBlank(message: 'Content is required')]
        #[Assert\Length(min: 10)]
        public readonly string $content,
    ) {
    }
}

