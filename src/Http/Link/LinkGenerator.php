<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Link;

use JsonApi\Symfony\Query\Pagination;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class LinkGenerator
{
    public function __construct(private readonly UrlGeneratorInterface $urls)
    {
    }

    public function topLevelSelf(Request $request): string
    {
        return $request->getUri();
    }

    public function resourceSelf(string $type, string $id): string
    {
        return $this->urls->generate(
            'jsonapi.resource',
            ['type' => $type, 'id' => $id],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    public function relationshipSelf(string $type, string $id, string $relationship): string
    {
        return $this->urls->generate(
            'jsonapi.relationship.get',
            ['type' => $type, 'id' => $id, 'rel' => $relationship],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    public function relationshipRelated(string $type, string $id, string $relationship): string
    {
        return $this->urls->generate(
            'jsonapi.related',
            ['type' => $type, 'id' => $id, 'rel' => $relationship],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    /**
     * @return array<string, string>
     */
    public function collectionPagination(string $type, Pagination $pagination, int $total, Request $request): array
    {
        /** @var array<string, mixed> $query */
        $query = $request->query->all();
        $links = [];
        $size = $pagination->size;
        $number = $pagination->number;
        $totalPages = max(1, (int) ceil($total / max($size, 1)));

        $links['first'] = $this->generateCollectionUrl($type, 1, $size, $query);
        $links['last'] = $this->generateCollectionUrl($type, $totalPages, $size, $query);

        if ($number > 1) {
            $links['prev'] = $this->generateCollectionUrl($type, max(1, $number - 1), $size, $query);
        }

        if ($number < $totalPages) {
            $links['next'] = $this->generateCollectionUrl($type, min($totalPages, $number + 1), $size, $query);
        }

        return $links;
    }

    /**
     * @param array<string, mixed> $query
     */
    private function generateCollectionUrl(string $type, int $number, int $size, array $query): string
    {
        if (!isset($query['page']) || !is_array($query['page'])) {
            $query['page'] = [];
        }

        $query['page']['number'] = $number;
        $query['page']['size'] = $size;
        $query['type'] = $type;

        return $this->urls->generate(
            'jsonapi.collection',
            $query,
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }
}
