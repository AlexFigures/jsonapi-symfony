<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Http\Exception;

use AlexFigures\Symfony\Http\Error\ErrorCodes;
use AlexFigures\Symfony\Http\Error\ErrorObject;
use AlexFigures\Symfony\Http\Error\ErrorTitles;

final class UnsupportedTransactionBoundaryException extends JsonApiHttpException
{
    public function __construct()
    {
        $detail = 'This operation spans unsupported transaction boundaries. The built-in Doctrine provider requires one EntityManager and one connection per transaction.';
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
