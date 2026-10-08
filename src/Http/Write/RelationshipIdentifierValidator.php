<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Write;

use AlexFigures\JsonApi\Http\Error\ErrorMapper;
use AlexFigures\JsonApi\Http\Exception\BadRequestException;
use AlexFigures\JsonApi\Http\Exception\ConflictException;

/** @internal Shared linkage structure and type validation. */
final class RelationshipIdentifierValidator
{
    /** @return array{type: string, id?: string, lid?: string} */
    public static function validate(mixed $data, ?string $expectedType, string $pointer, ErrorMapper $errors, bool $allowLid = false, int $conflictStatus = 409): array
    {
        if (!is_array($data) || array_is_list($data)) {
            throw new BadRequestException('Invalid resource identifier.', [$errors->invalidPointer($pointer, 'Expected a resource identifier object.')]);
        }
        if (isset($data['id'], $data['lid'])) {
            throw new BadRequestException('Identifiers cannot contain both id and lid.', [$errors->invalidPointer($pointer, 'Use either id or lid.')]);
        }
        $type = $data['type'] ?? null;
        if (!is_string($type) || $type === '') {
            throw new BadRequestException('Invalid resource type.', [$errors->invalidPointer($pointer . '/type', 'Expected a non-empty resource type.')]);
        }
        $member = $allowLid && isset($data['lid']) ? 'lid' : 'id';
        $id = $data[$member] ?? null;
        if (!is_string($id) || $id === '') {
            throw new BadRequestException('Invalid resource identifier.', [$errors->invalidPointer($pointer . '/' . $member, 'Expected a non-empty string identifier.')]);
        }
        if ($expectedType !== null && $type !== $expectedType) {
            $error = $errors->invalidPointer($pointer . '/type', 'Relationship type mismatch.', (string) $conflictStatus, \AlexFigures\JsonApi\Http\Error\ErrorCodes::TYPE_MISMATCH);
            throw $conflictStatus === 409 ? new ConflictException('Relationship type mismatch.', [$error]) : new BadRequestException('Relationship type mismatch.', [$error]);
        }
        return ['type' => $type, $member => $id];
    }
}
