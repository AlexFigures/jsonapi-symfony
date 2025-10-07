<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

/**
 * Интерфейс для Repository, который поддерживает конкретные типы ресурсов.
 * 
 * Используется в системе тегов для регистрации per-type репозиториев.
 */
interface TypedResourceRepository extends ResourceRepository
{
    /**
     * Проверяет, поддерживает ли этот репозиторий указанный тип ресурса.
     *
     * @param string $type JSON:API тип ресурса (например, 'articles', 'users')
     * @return bool true, если репозиторий поддерживает этот тип
     */
    public function supports(string $type): bool;
}

