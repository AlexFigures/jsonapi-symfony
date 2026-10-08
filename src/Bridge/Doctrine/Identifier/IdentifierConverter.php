<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Bridge\Doctrine\Identifier;

use AlexFigures\JsonApi\Http\Error\ErrorObject;
use AlexFigures\JsonApi\Http\Error\ErrorSource;
use AlexFigures\JsonApi\Http\Exception\BadRequestException;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;

/** @internal */
final class IdentifierConverter
{
    /** @param class-string $class */
    public static function convert(EntityManagerInterface $em, string $class, string $id, string $pointer = '/data/id'): mixed
    {
        $metadata = $em->getClassMetadata($class);
        $field = $metadata->getSingleIdentifierFieldName();
        $typeName = $metadata->getTypeOfField($field);
        if ($typeName === null) {
            return $id;
        }
        $platform = $em->getConnection()->getDatabasePlatform();
        try {
            if (in_array($typeName, ['integer', 'smallint', 'bigint'], true) && !preg_match('/^-?[0-9]+$/D', $id)) {
                throw new \InvalidArgumentException('Expected an integer identifier.');
            }
            return Type::getType($typeName)->convertToPHPValue($id, $platform);
        } catch (\Doctrine\DBAL\Exception|\InvalidArgumentException $exception) {
            $error = new ErrorObject(null, null, '400', 'invalid-identifier', 'Invalid Identifier', 'The resource identifier is invalid.', new ErrorSource(pointer: $pointer));
            throw new BadRequestException('Invalid resource identifier.', [$error], previous: $exception);
        }
    }
}
