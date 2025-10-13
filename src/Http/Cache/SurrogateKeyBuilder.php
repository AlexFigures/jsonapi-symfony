<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Cache;

use Symfony\Component\HttpFoundation\Request;

/**
 * @phpstan-type SurrogateKeyConfig array{
 *     surrogate_keys?: array{
 *         format?: array{resource?: string, collection?: string, relationship?: string}
 *     },
 *     format?: array{resource?: string, collection?: string, relationship?: string}
 * }
 */
final class SurrogateKeyBuilder
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
        $route = $request->attributes->get('_route');
        $type = $request->attributes->get('type');
        $id = $request->attributes->get('id');
        $relationship = $request->attributes->get('relationship');

        $route = is_string($route) ? $route : '';
        $type = is_string($type) ? $type : '';
        $id = is_scalar($id) ? (string) $id : '';
        $relationship = is_scalar($relationship) ? (string) $relationship : '';

        if ($route === 'jsonapi.collection') {
            return $type === '' ? [] : [$this->format($this->collectionFormat, $type, $id, $relationship)];
        }

        if ($route === 'jsonapi.resource' || $route === 'jsonapi.related' || str_contains($route, 'relationship')) {
            $keys = [];
            if ($type !== '') {
                $keys[] = $this->format($this->collectionFormat, $type, $id, $relationship);
            }

            if ($type !== '' && $id !== '') {
                $keys[] = $this->format($this->resourceFormat, $type, $id, $relationship);
            }

            if ($relationship !== '' && $type !== '' && $id !== '') {
                $keys[] = $this->format($this->relationshipFormat, $type, $id, $relationship);
            }

            return array_values(array_unique($keys));
        }

        return [];
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
