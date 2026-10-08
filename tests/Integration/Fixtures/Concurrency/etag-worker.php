<?php

declare(strict_types=1);

use AlexFigures\JsonApi\Tests\Integration\Fixtures\Concurrency\WritePreconditionsHarness;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

require dirname(__DIR__, 3) . '/bootstrap.php';

$input = json_decode((string) fgets(\STDIN), true, 512, \JSON_THROW_ON_ERROR);
$config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__) . '/Entity'], true);
\AlexFigures\JsonApi\Tests\Integration\Fixtures\DoctrineConfiguration::configureLazyObjects($config);
$manager = new EntityManager(\AlexFigures\JsonApi\Tests\Integration\Fixtures\ConnectionFactory::create(['url' => $input['dsn']]), $config);
$harness = new WritePreconditionsHarness($manager);
// Prime this worker's identity map before either writer begins.
$etag = $harness->request('GET', $input['id'])->getEtag();
$pid = $manager->getConnection()->fetchOne('SELECT pg_backend_pid()');
echo json_encode(['ready' => true, 'etag' => $etag, 'pid' => $pid], \JSON_THROW_ON_ERROR), "\n";
fflush(\STDOUT);
if (trim((string) fgets(\STDIN)) !== 'GO') {
    throw new RuntimeException('Missing concurrent-start barrier.');
}
$response = $harness->request('PATCH', $input['id'], $input['etag'], $input['name']);
echo json_encode(['status' => $response->getStatusCode(), 'body' => json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)], \JSON_THROW_ON_ERROR), "\n";
$manager->getConnection()->close();
