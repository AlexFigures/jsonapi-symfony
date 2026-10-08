<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Tests\Integration\Fixtures\Metadata;

use AlexFigures\JsonApi\Resource\Metadata\AttributeMetadata;
use AlexFigures\JsonApi\Resource\Metadata\ResourceMetadata;
use AlexFigures\JsonApi\Tests\Integration\Fixtures\Entity\ValidatedArticle;

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
