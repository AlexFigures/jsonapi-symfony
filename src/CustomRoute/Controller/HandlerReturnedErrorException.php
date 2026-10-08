<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\CustomRoute\Controller;

use AlexFigures\JsonApi\CustomRoute\Result\CustomRouteResult;

/**
 * Internal exception used to signal that a handler returned an error result.
 *
 * This is used to trigger transaction rollback when a handler returns an
 * error result instead of throwing an exception.
 *
 * @internal
 */
final class HandlerReturnedErrorException extends \RuntimeException
{
    public function __construct(
        private readonly CustomRouteResult $result,
    ) {
        parent::__construct('Handler returned an error result');
    }

    public function getResult(): CustomRouteResult
    {
        return $this->result;
    }
}
