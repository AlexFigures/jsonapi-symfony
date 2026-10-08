<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Cache;

use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** @internal */
final readonly class LastModifiedResolver
{
    /** @param array{last_modified?: array{resource_field?: string, per_type?: array<string, string>, collections_max_of?: bool}} $config */
    public function __construct(private array $config = [])
    {
    }

    public function resolve(Request $request, Response $response): ?DateTimeImmutable
    {
        $header = $response->headers->get('Last-Modified');
        if ($header !== null) {
            $time = strtotime($header);
            if ($time !== false) {
                return new DateTimeImmutable('@' . $time);
            }
        }

        $type = $request->attributes->get('_jsonapi_model_type');
        $settings = $this->config['last_modified'] ?? [];
        $route = $request->attributes->get('_route');
        if (($settings['collections_max_of'] ?? true) === false && ($request->attributes->getBoolean('_jsonapi_collection') || (is_string($route) && str_ends_with($route, '.index')) || $request->attributes->get('_route') === 'jsonapi.collection')) {
            return null;
        }
        $field = (is_string($type) ? ($settings['per_type'][$type] ?? null) : null) ?? $settings['resource_field'] ?? 'updatedAt';
        $models = $request->attributes->get('_jsonapi_models', []);
        $accessor = \Symfony\Component\PropertyAccess\PropertyAccess::createPropertyAccessor();
        $latest = null;
        if (is_array($models)) {
            foreach ($models as $model) {
                if (!is_object($model) || !$accessor->isReadable($model, $field)) {
                    continue;
                }
                $value = $accessor->getValue($model, $field);
                if ($value instanceof \DateTimeInterface && ($latest === null || $value->getTimestamp() > $latest->getTimestamp())) {
                    $latest = DateTimeImmutable::createFromInterface($value);
                }
            }
        }
        return $latest ?? new DateTimeImmutable();
    }
}
