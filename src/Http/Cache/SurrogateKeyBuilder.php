<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Cache;

use Symfony\Component\HttpFoundation\Request;

final class SurrogateKeyBuilder
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $format = $config['surrogate_keys']['format'] ?? ($config['format'] ?? []);
        $this->resourceFormat = $format['resource'] ?? '{type}:{id}';
        $this->collectionFormat = $format['collection'] ?? '{type}';
        $this->relationshipFormat = $format['relationship'] ?? '{type}:{id}:{rel}';
    }

    private string $resourceFormat;

    private string $collectionFormat;

    private string $relationshipFormat;

    public function build(Request $request): array
    {
        $route = (string) $request->attributes->get('_route', '');
        $type = (string) $request->attributes->get('type', '');
        $id = (string) $request->attributes->get('id', '');
        $relationship = (string) $request->attributes->get('relationship', '');

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
