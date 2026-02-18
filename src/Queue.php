<?php

namespace ThermaFlow;

use ThermaFlow\Contracts\QueueDriver;

class Queue
{
    protected QueueDriver $driver;

    public function __construct(QueueDriver $driver)
    {
        $this->driver = $driver;
    }

    /**
     * Push a job onto the queue.
     */
    public function push(string $jobClass, array $data = [], string $queue = 'default'): bool
    {
        $payload = json_encode([
            'job' => $jobClass,
            'data' => $data,
            'created_at' => time(),
        ]);

        return $this->driver->push($queue, $payload);
    }
}
