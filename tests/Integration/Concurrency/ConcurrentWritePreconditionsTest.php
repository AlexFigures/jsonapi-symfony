<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Tests\Integration\Concurrency;

use AlexFigures\Symfony\Tests\Integration\Atomic\DoctrineAtomicTestCase;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Concurrency\WritePreconditionsHarness;
use AlexFigures\Symfony\Tests\Integration\Fixtures\Entity\GeneratedRecord;

final class ConcurrentWritePreconditionsTest extends DoctrineAtomicTestCase
{
    public function testIndependentConcurrentPatchRequestsRevalidateAfterWaitingForRowLock(): void
    {
        $record = $this->record();
        $harness = new WritePreconditionsHarness($this->em);
        $etag = $harness->request('GET', (string) $record->id)->getEtag();
        self::assertNotNull($etag);
        $connection = $this->em->getConnection();
        $workers = [];
        $results = [];
        $connection->beginTransaction();
        $connection->fetchOne('SELECT id FROM generated_records WHERE id = ? FOR UPDATE', [$record->id]);
        try {
            foreach (['Writer A', 'Writer B'] as $name) {
                $process = proc_open([\PHP_BINARY, dirname(__DIR__) . '/Fixtures/Concurrency/etag-worker.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                self::assertIsResource($process);
                $workers[] = [$process, $pipes];
                stream_set_timeout($pipes[1], 15);
                fwrite($pipes[0], json_encode(['dsn' => $this->getDatabaseUrl(), 'id' => (string) $record->id, 'etag' => $etag, 'name' => $name], \JSON_THROW_ON_ERROR) . "\n");
            }
            $pids = [];
            foreach ($workers as [$process, $pipes]) {
                $ready = fgets($pipes[1]);
                self::assertNotFalse($ready, 'Worker failed to initialize.');
                $ready = json_decode($ready, true, 512, \JSON_THROW_ON_ERROR);
                self::assertSame($etag, $ready['etag']);
                $pids[] = (int) $ready['pid'];
            }
            self::assertNotSame($pids[0], $pids[1], 'Writers must have independent physical connections.');
            foreach ($workers as [$process, $pipes]) {
                fwrite($pipes[0], "GO\n");
                fflush($pipes[0]);
            }
            // Prove both requests are waiting on the root lock, rather than accidentally sequential.
            $deadline = microtime(true) + 10;
            do {
                $waiting = (int) $connection->fetchOne("SELECT COUNT(*) FROM pg_stat_activity WHERE pid IN (?, ?) AND wait_event_type = 'Lock'", $pids);
                if ($waiting === 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::assertSame(2, $waiting, 'Both PATCH requests must reach the row-lock barrier.');
            $connection->commit();
            foreach ($workers as [$process, $pipes]) {
                $output = fgets($pipes[1]);
                self::assertNotFalse($output, 'Worker failed to return a response.');
                $results[] = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
            }
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            foreach ($workers as [$process, $pipes]) {
                proc_terminate($process);
                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }
                proc_close($process);
            }
        }
        $statuses = array_column($results, 'status');
        sort($statuses);
        self::assertSame([200, 412], $statuses);
        $winner = array_values(array_filter($results, static fn (array $result): bool => $result['status'] === 200))[0];
        $loser = array_values(array_filter($results, static fn (array $result): bool => $result['status'] === 412))[0];
        self::assertSame('412', $loser['body']['errors'][0]['status']);
        $this->em->clear();
        self::assertSame($winner['body']['data']['attributes']['name'], $this->em->find(GeneratedRecord::class, $record->id)->name);
    }

    public function testGuardedHttpPreconditionsAndWildcard(): void
    {
        $record = $this->record();
        $harness = new WritePreconditionsHarness($this->em, true);
        $missing = $harness->request('PATCH', (string) $record->id);
        self::assertSame(428, $missing->getStatusCode());
        $error = json_decode((string) $missing->getContent(), true, 512, \JSON_THROW_ON_ERROR)['errors'][0];
        self::assertSame('428', $error['status']);
        self::assertSame('If-Match', $error['source']['header']);
        self::assertSame('Original', $this->em->getConnection()->fetchOne('SELECT name FROM generated_records WHERE id = ?', [$record->id]));
        // A rejected transaction closes its manager. Reopen using an independent ORM unit of work.
        $manager = new \Doctrine\ORM\EntityManager($this->em->getConnection(), $this->em->getConfiguration());
        $harness = new WritePreconditionsHarness($manager, true);
        $etag = $harness->request('GET', (string) $record->id)->getEtag();
        self::assertSame(200, $harness->request('PATCH', (string) $record->id, $etag, 'Matching')->getStatusCode());
        self::assertSame(200, $harness->request('PATCH', (string) $record->id, '*', 'Wildcard')->getStatusCode());
        self::assertSame(412, $harness->request('DELETE', (string) $record->id, $etag)->getStatusCode());
        self::assertSame('Wildcard', $manager->getConnection()->fetchOne('SELECT name FROM generated_records WHERE id = ?', [$record->id]));
    }

    private function record(): GeneratedRecord
    {
        $record = new GeneratedRecord();
        $record->name = 'Original';
        $this->em->persist($record);
        $this->em->flush();
        return $record;
    }
}
