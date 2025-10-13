<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Atomic\Parser;

use AlexFigures\Symfony\Atomic\AtomicConfig;
use AlexFigures\Symfony\Atomic\Operation;
use AlexFigures\Symfony\Atomic\Ref;
use AlexFigures\Symfony\Http\Error\ErrorMapper;
use AlexFigures\Symfony\Http\Exception\BadRequestException;
use AlexFigures\Symfony\Http\Negotiation\MediaType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class AtomicRequestParser
{
    public function __construct(
        private readonly AtomicConfig $config,
        private readonly ErrorMapper $errors,
    ) {
    }

    /**
     * @return list<Operation>
     */
    public function parse(Request $request): array
    {
        $payload = $this->decode($request);

        if (!isset($payload['atomic:operations'])) {
            throw new BadRequestException('Missing atomic operations.', [
                $this->errors->invalidPointer('/atomic:operations', 'The request body MUST contain an "atomic:operations" member.'),
            ]);
        }

        $operations = $payload['atomic:operations'];
        if (!is_array($operations) || !array_is_list($operations)) {
            throw new BadRequestException('atomic:operations must be a list.', [
                $this->errors->invalidPointer('/atomic:operations', 'The "atomic:operations" member MUST be an array of operation objects.'),
            ]);
        }

        if ($operations === []) {
            throw new BadRequestException('atomic:operations cannot be empty.', [
                $this->errors->invalidPointer('/atomic:operations', 'The "atomic:operations" array MUST contain at least one operation.'),
            ]);
        }

        if (count($operations) > $this->config->maxOperations) {
            throw new BadRequestException('Too many operations.', [
                $this->errors->invalidPointer('/atomic:operations', sprintf('No more than %d operations are allowed per request.', $this->config->maxOperations)),
            ], headers: ['Content-Type' => MediaType::JSON_API_ATOMIC]);
        }

        $parsed = [];
        foreach ($operations as $index => $operation) {
            if (!is_array($operation) || array_is_list($operation)) {
                throw new BadRequestException('Invalid atomic operation.', [
                    $this->errors->invalidPointer(sprintf('/atomic:operations/%d', $index), 'Each operation MUST be an object.'),
                ]);
            }

            $pointer = sprintf('/atomic:operations/%d', $index);
            $op = $operation['op'] ?? null;
            if (!is_string($op) || $op === '') {
                throw new BadRequestException('Invalid op member.', [
                    $this->errors->invalidPointer($pointer . '/op', 'Each operation MUST contain a non-empty "op" member.'),
                ]);
            }

            $ref = null;
            if (array_key_exists('ref', $operation)) {
                $ref = $this->parseRef($operation['ref'], $pointer . '/ref');
            }

            $href = null;
            if (array_key_exists('href', $operation)) {
                $href = $operation['href'];
                if (!is_string($href) || $href === '') {
                    throw new BadRequestException('Invalid href member.', [
                        $this->errors->invalidPointer($pointer . '/href', 'The "href" member MUST be a non-empty string when present.'),
                    ]);
                }
            }

            $data = $operation['data'] ?? null;
            if (array_key_exists('data', $operation) && !is_array($data) && $data !== null) {
                throw new BadRequestException('Invalid data member.', [
                    $this->errors->invalidPointer($pointer . '/data', 'The "data" member MUST be either null or an object/array.'),
                ]);
            }

            $meta = $operation['meta'] ?? [];
            if (!is_array($meta)) {
                throw new BadRequestException('Invalid meta member.', [
                    $this->errors->invalidPointer($pointer . '/meta', 'The "meta" member MUST be an object when present.'),
                ]);
            }

            /** @var array<string, mixed> $meta */
            $parsed[] = new Operation($op, $ref, $href, $data, $meta, $pointer);
        }

        return $parsed;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Request $request): array
    {
        $content = (string) $request->getContent();
        if ($content === '') {
            throw new BadRequestException('Request body must not be empty.', [
                $this->errors->invalidPointer('/', 'Request body must not be empty.'),
            ], headers: ['Content-Type' => MediaType::JSON_API_ATOMIC]);
        }

        try {
            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new BadRequestException('Malformed JSON.', [
                $this->errors->invalidJson($exception),
            ], headers: ['Content-Type' => MediaType::JSON_API_ATOMIC], previous: $exception);
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new BadRequestException('Request body must be a valid JSON object.', [
                $this->errors->invalidPointer('/', 'Request body must be a valid JSON object.'),
            ], headers: ['Content-Type' => MediaType::JSON_API_ATOMIC]);
        }

        if (isset($decoded['data']) || isset($decoded['included'])) {
            throw new BadRequestException('JSON:API atomic documents MUST NOT contain top-level data or included members.', [
                $this->errors->invalidPointer('/', 'Atomic operations documents MUST only contain the "atomic:operations" member.'),
            ], headers: ['Content-Type' => MediaType::JSON_API_ATOMIC]);
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function parseRef(mixed $value, string $pointer): Ref
    {
        if (!is_array($value) || array_is_list($value)) {
            throw new BadRequestException('Invalid ref member.', [
                $this->errors->invalidPointer($pointer, 'The "ref" member MUST be an object.'),
            ]);
        }

        $type = $value['type'] ?? null;
        if (!is_string($type) || $type === '') {
            throw new BadRequestException('Invalid ref type.', [
                $this->errors->invalidPointer($pointer . '/type', 'The "ref.type" member MUST be a non-empty string.'),
            ]);
        }

        $id = null;
        if (array_key_exists('id', $value)) {
            $id = $value['id'];
            if (!is_string($id) || $id === '') {
                throw new BadRequestException('Invalid ref id.', [
                    $this->errors->invalidPointer($pointer . '/id', 'The "ref.id" member MUST be a non-empty string when present.'),
                ]);
            }
        }

        $lid = null;
        if (array_key_exists('lid', $value)) {
            $lid = $value['lid'];
            if (!is_string($lid) || $lid === '') {
                throw new BadRequestException('Invalid ref lid.', [
                    $this->errors->invalidPointer($pointer . '/lid', 'The "ref.lid" member MUST be a non-empty string when present.'),
                ]);
            }
        }

        $relationship = null;
        if (array_key_exists('relationship', $value)) {
            $relationship = $value['relationship'];
            if (!is_string($relationship) || $relationship === '') {
                throw new BadRequestException('Invalid ref relationship.', [
                    $this->errors->invalidPointer($pointer . '/relationship', 'The "ref.relationship" member MUST be a non-empty string when present.'),
                ]);
            }
        }

        return new Ref($type, $id, $lid, $relationship);
    }
}
