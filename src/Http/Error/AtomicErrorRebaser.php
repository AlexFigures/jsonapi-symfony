<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Error;

use AlexFigures\Symfony\Http\Exception\JsonApiHttpException;

/** @internal */
final class AtomicErrorRebaser
{
    public static function rebase(JsonApiHttpException $exception, string $prefix, bool $batch = false): JsonApiHttpException
    {
        $errors = [];
        foreach ($exception->getErrors() as $error) {
            $source = $error->source;
            if ($source?->pointer !== null && !str_starts_with($source->pointer, '/atomic:operations')) {
                $source = new ErrorSource(($batch ? $prefix : $prefix . $source->pointer), $source->parameter, $source->header);
            }
            $errors[] = new ErrorObject($error->id, $error->aboutLink, $error->status, $error->code, $error->title, $error->detail, $source, $error->meta);
        }
        return $exception->withErrors($errors);
    }
}
