<?php

declare(strict_types=1);

use JsonApi\Symfony\StressApp\Kernel;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__, 4) . '/vendor/autoload.php';

$kernel = new Kernel('dev', true);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);

