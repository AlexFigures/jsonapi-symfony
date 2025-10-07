<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Contract\Data;

/**
 * Интерфейс для Persister, который поддерживает конкретные типы ресурсов.
 * 
 * Используется в системе тегов для регистрации per-type персистеров.
 */
interface TypedResourcePersister extends ResourcePersister
{
    /**
     * Проверяет, поддерживает ли этот персистер указанный тип ресурса.
     *
     * @param string $type JSON:API тип ресурса (например, 'articles', 'users')
     * @return bool true, если персистер поддерживает этот тип
     */
    public function supports(string $type): bool;
}

