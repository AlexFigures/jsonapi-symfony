<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

/**
 * Интерфейс для RelationshipUpdater, который поддерживает конкретные типы ресурсов.
 * 
 * Используется в системе тегов для регистрации per-type updaters.
 */
interface TypedRelationshipUpdater extends RelationshipUpdater
{
    /**
     * Проверяет, поддерживает ли этот updater указанный тип ресурса.
     *
     * @param string $type JSON:API тип ресурса (например, 'articles', 'users')
     * @return bool true, если updater поддерживает этот тип
     */
    public function supports(string $type): bool;
}

