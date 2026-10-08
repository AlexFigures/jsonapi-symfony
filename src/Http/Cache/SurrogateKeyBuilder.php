<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Cache;

use Symfony\Component\HttpFoundation\Request;

/**
 * @phpstan-type SurrogateKeyConfig array{
 *     surrogate_keys?: array{
 *         format?: array{resource?: string, collection?: string, relationship?: string}
 *     },
 *     format?: array{resource?: string, collection?: string, relationship?: string}
 * }
 * @internal
 */
final readonly class SurrogateKeyBuilder
{
    /**
     * @param SurrogateKeyConfig $config
     */
    public function __construct(array $config = [])
    {
        if (isset($config['surrogate_keys']['format'])) {
            $format = $config['surrogate_keys']['format'];
        } elseif (isset($config['format'])) {
            $format = $config['format'];
        } else {
            $format = [];
        }

        $this->resourceFormat = isset($format['resource']) ? (string) $format['resource'] : '{type}:{id}';
        $this->collectionFormat = isset($format['collection']) ? (string) $format['collection'] : '{type}';
        $this->relationshipFormat = isset($format['relationship']) ? (string) $format['relationship'] : '{type}:{id}:{rel}';
    }

    private string $resourceFormat;

    private string $collectionFormat;

    private string $relationshipFormat;

    /**
     * @return list<string>
     */
    public function build(Request $request): array
    {
        $type = $request->attributes->get('type');
        if (!is_string($type) || $type === '') {
            return [];
        }
        $rawId = $request->attributes->get('id');
        $id = is_scalar($rawId) ? (string) $rawId : '';
        $rawRelationship = $request->attributes->get('relationship', $request->attributes->get('rel'));
        $relationship = is_scalar($rawRelationship) ? (string) $rawRelationship : '';
        $keys = [$this->format($this->collectionFormat, $type, $id, $relationship)];
        if ($id !== '') {
            $keys[] = $this->format($this->resourceFormat, $type, $id, $relationship);
            if ($relationship !== '') {
                $keys[] = $this->format($this->relationshipFormat, $type, $id, $relationship);
            }
        }
        return array_values(array_unique($keys));
    }

    private function format(string $format, string $type, string $id, string $relationship): string
    {
        return strtr($format, [
            '{type}' => $type,
            '{id}' => $id,
            '{rel}' => $relationship,
        ]);
    }
}
