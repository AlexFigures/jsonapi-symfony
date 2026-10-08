<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Bridge\Doctrine\Identifier;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/** @internal Converts identifiers before array binding, including binary UUID types. */
final class IdentifierParameters
{
    /** @param class-string $class
     * @param list<string> $ids
     */
    public static function bind(QueryBuilder $query, EntityManagerInterface $em, string $class, string $name, array $ids): void
    {
        $metadata = $em->getClassMetadata($class);
        $type = $metadata->getTypeOfField($metadata->getSingleIdentifierFieldName());
        $values = [];
        foreach ($ids as $id) {
            $value = IdentifierConverter::convert($em, $class, $id);
            $values[] = $type === null ? $value : Type::getType($type)->convertToDatabaseValue($value, $em->getConnection()->getDatabasePlatform());
        }
        $query->setParameter($name, $values, in_array($type, ['integer', 'smallint'], true) ? ArrayParameterType::INTEGER : ArrayParameterType::STRING);
    }
}
