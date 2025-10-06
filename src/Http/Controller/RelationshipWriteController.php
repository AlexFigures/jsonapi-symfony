<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Controller;

use JsonApi\Symfony\Contract\Data\RelationshipUpdater;
use JsonApi\Symfony\Http\Error\ErrorMapper;
use JsonApi\Symfony\Http\Exception\BadRequestException;
use JsonApi\Symfony\Http\Exception\UnsupportedMediaTypeException;
use JsonApi\Symfony\Http\Negotiation\MediaType;
use JsonApi\Symfony\Http\Relationship\LinkageBuilder;
use JsonApi\Symfony\Http\Relationship\WriteRelationshipsResponseConfig;
use JsonApi\Symfony\Http\Write\RelationshipDocumentValidator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use RuntimeException;

#[Route(path: '/api/{type}/{id}/relationships/{rel}', methods: ['PATCH', 'POST', 'DELETE'], name: 'jsonapi.relationship.write')]
final class RelationshipWriteController
{
    public function __construct(
        private readonly RelationshipDocumentValidator $validator,
        private readonly RelationshipUpdater $updater,
        private readonly LinkageBuilder $linkage,
        private readonly WriteRelationshipsResponseConfig $responseConfig,
        private readonly ErrorMapper $errors,
    ) {
    }

    public function __invoke(Request $request, string $type, string $id, string $rel): Response
    {
        $payload = $this->decode($request);
        /** @var array{kind: 'to-one'|'to-many', data: null|array{type: string, id: string}|list<array{type: string, id: string}>} $validated */
        $validated = $this->validator->validate($type, $id, $rel, $payload, $request->getMethod());
        $kind = $validated['kind'];
        $data = $validated['data'];

        if ($request->isMethod('PATCH')) {
            if ($kind === 'to-one') {
                /** @var array{type: string, id: string}|null $data */
                $this->updater->replaceToOne($type, $id, $rel, $this->linkage->toIdentifierOrNull($data));
            } else {
                /** @var list<array{type: string, id: string}> $data */
                $this->updater->replaceToMany($type, $id, $rel, $this->linkage->toIdentifiers($data));
            }
        } elseif ($request->isMethod('POST')) {
            /** @var list<array{type: string, id: string}> $data */
            $this->updater->addToMany($type, $id, $rel, $this->linkage->toIdentifiers($data));
        } else {
            /** @var list<array{type: string, id: string}> $data */
            $this->updater->removeFromToMany($type, $id, $rel, $this->linkage->toIdentifiers($data));
        }

        if ($this->responseConfig->mode === '204') {
            return new Response(null, Response::HTTP_NO_CONTENT, ['Content-Type' => MediaType::JSON_API]);
        }

        [, $data] = $this->linkage->read($type, $id, $rel, $request);

        return new JsonResponse(
            [
                'jsonapi' => ['version' => '1.1'],
                'links' => ['self' => $request->getUri()],
                'data' => $data,
            ],
            JsonResponse::HTTP_OK,
            ['Content-Type' => MediaType::JSON_API],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(Request $request): ?array
    {
        $contentType = $request->headers->get('Content-Type');
        if ($contentType !== null && $this->normalizeMediaType($contentType) !== MediaType::JSON_API) {
            throw new UnsupportedMediaTypeException($contentType, 'JSON:API requires the "application/vnd.api+json" media type.');
        }

        $content = (string) $request->getContent();

        if ($content === '') {
            return null;
        }

        $decoded = json_decode($content, true);
        if ($decoded === null && json_last_error() !== \JSON_ERROR_NONE) {
            $error = $this->errors->invalidJson(new RuntimeException(sprintf('Malformed JSON: %s.', json_last_error_msg())));
            throw new BadRequestException('Malformed JSON.', [$error]);
        }

        if ($decoded === null) {
            return null;
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new BadRequestException('Request body must be a valid JSON object.', [$this->errors->invalidPointer('/', 'Request body must be a valid JSON object.')]);
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function normalizeMediaType(string $value): string
    {
        $normalized = trim(strtolower($value));
        $semicolonPosition = strpos($normalized, ';');

        if ($semicolonPosition === false) {
            return $normalized;
        }

        return substr($normalized, 0, $semicolonPosition);
    }
}
