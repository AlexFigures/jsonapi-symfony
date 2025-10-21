<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Fixtures\Metadata;

use AlexFigures\Symfony\Resource\Metadata\AttributeMetadata;
use AlexFigures\Symfony\Resource\Metadata\ResourceMetadata;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\ValidatedArticle;

final class ValidatedArticleMetadata
{
    public static function create(): ResourceMetadata
    {
        return new ResourceMetadata(
            type: 'validated-articles',
            class: ValidatedArticle::class,
            attributes: [
                'title' => new AttributeMetadata('title', 'string', true, false),
                'content' => new AttributeMetadata('content', 'string', false, false),
                'contactEmail' => new AttributeMetadata('contactEmail', 'string', false, false),
                'status' => new AttributeMetadata('status', 'string', false, false),
                'priority' => new AttributeMetadata('priority', 'integer', false, false),
                'publishedAt' => new AttributeMetadata('publishedAt', 'datetime', false, false),
            ],
            relationships: [],
        );
    }
}
