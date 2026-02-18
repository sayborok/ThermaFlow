<?php

namespace ThermaFlow\Drivers;

use ThermaFlow\Contracts\QueueDriver;
use PDO;

class SQLiteDriver implements QueueDriver
{
    protected PDO $database;

    public function __construct(string $databasePath)
    {
        $this->database = new PDO("sqlite:{$databasePath}");
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->setupTable();
    }

    protected function setupTable(): void
    {
        $this->database->exec("
            CREATE TABLE IF NOT EXISTS jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                queue TEXT,
                payload TEXT,
                attempts INTEGER DEFAULT 0,
                reserved_at INTEGER DEFAULT NULL,
                available_at INTEGER,
                created_at INTEGER
            )
        ");

        $this->database->exec("CREATE INDEX IF NOT EXISTS queue_index ON jobs(queue, reserved_at, available_at)");
    }

    public function push(string $queue, string $payload): bool
    {
        $stmt = $this->database->prepare("INSERT INTO jobs (queue, payload, available_at, created_at) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$queue, $payload, time(), time()]);
    }

    public function pop(string $queue): ?object
    {
        $this->database->beginTransaction();

        try {
            $stmt = $this->database->prepare("
                SELECT * FROM jobs 
                WHERE queue = ? 
                AND (reserved_at IS NULL OR reserved_at < ?) 
                AND available_at <= ? 
                ORDER BY id ASC 
                LIMIT 1
            ");

            $now = time();
            $stmt->execute([$queue, $now - 60, $now]);
            $job = $stmt->fetch(PDO::FETCH_OBJ);

            if ($job) {
                $update = $this->database->prepare("UPDATE jobs SET reserved_at = ?, attempts = attempts + 1 WHERE id = ?");
                $update->execute([$now, $job->id]);
                $this->database->commit();
                return $job;
            }

            $this->database->rollBack();
            return null;
        } catch (\Exception $e) {
            $this->database->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        $stmt = $this->database->prepare("DELETE FROM jobs WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function release(int $id, int $delay): bool
    {
        $stmt = $this->database->prepare("UPDATE jobs SET reserved_at = NULL, available_at = ? WHERE id = ?");
        return $stmt->execute([time() + $delay, $id]);
    }

    public function size(string $queue): int
    {
        $stmt = $this->database->prepare("SELECT COUNT(*) FROM jobs WHERE queue = ?");
        $stmt->execute([$queue]);
        return (int) $stmt->fetchColumn();
    }
}
