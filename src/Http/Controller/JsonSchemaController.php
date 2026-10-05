<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Controller;

use AlexFigures\Symfony\Docs\OpenApi\OpenApiSpecGenerator;
use AlexFigures\Symfony\Profile\ProfileRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @internal */
final readonly class JsonSchemaController
{
    /** @param array{enabled?: bool, include_profiles?: bool, route?: string} $config */
    public function __construct(private OpenApiSpecGenerator $generator, private array $config, private ?ProfileRegistry $profiles = null)
    {
    }

    public function __invoke(): JsonResponse
    {
        if (!($this->config['enabled'] ?? false)) {
            throw new NotFoundHttpException('JSON Schema generation is disabled.');
        }
        $schema = $this->generator->generateJsonSchema();
        if ($this->config['include_profiles'] ?? true) {
            $schema['x-jsonapi-profiles'] = array_map(static fn ($profile): string => $profile->uri(), $this->profiles?->all() ?? []);
        }
        return new JsonResponse($schema, headers: ['Content-Type' => 'application/schema+json']);
    }
}
