<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

/**
 * Интерфейс для RelationshipReader, который поддерживает конкретные типы ресурсов.
 * 
 * Используется в системе тегов для регистрации per-type readers.
 */
interface TypedRelationshipReader extends RelationshipReader
{
    /**
     * Проверяет, поддерживает ли этот reader указанный тип ресурса.
     *
     * @param string $type JSON:API тип ресурса (например, 'articles', 'users')
     * @return bool true, если reader поддерживает этот тип
     */
    public function supports(string $type): bool;
}

