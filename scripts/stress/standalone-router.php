<?php
// Router script for PHP built-in server
require_once __DIR__ . '/standalone-server.php';

$request = Symfony\Component\HttpFoundation\Request::createFromGlobals();
$response = handleRequest($request, $GLOBALS['collectionController'], $GLOBALS['resourceController']);
$response->send();