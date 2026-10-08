<?php

declare(strict_types=1);

namespace AlexFigures\JsonApi\Http\Exception;

use AlexFigures\JsonApi\Http\Error\ErrorCodes;
use AlexFigures\JsonApi\Http\Error\ErrorObject;
use AlexFigures\JsonApi\Http\Error\ErrorTitles;

/** @api */
final class UnsupportedTransactionBoundaryException extends JsonApiHttpException
{
    public function __construct()
    {
        $detail = 'This operation cannot execute within one supported transaction boundary. The built-in Doctrine provider requires one EntityManager and one connection per transaction.';
        parent::__construct(409, $detail, errors: [new ErrorObject(
            null,
            null,
            '409',
            ErrorCodes::UNSUPPORTED_TRANSACTION_BOUNDARY,
            ErrorTitles::MAP[ErrorCodes::UNSUPPORTED_TRANSACTION_BOUNDARY],
            $detail,
            null,
        )]);
    }
}
