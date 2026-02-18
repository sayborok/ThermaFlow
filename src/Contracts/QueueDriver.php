<?php

namespace ThermaFlow\Contracts;

interface QueueDriver
{
    /**
     * Push a job to the queue.
     */
    public function push(string $queue, string $payload): bool;

    /**
     * Pop a job from the queue.
     */
    public function pop(string $queue): ?object;

    /**
     * Get the current size of the queue.
     */
    public function size(string $queue): int;

    /**
     * Delete a job from the queue.
     */
    public function delete(int $id): bool;

    /**
     * Release a job back to the queue with a delay.
     */
    public function release(int $id, int $delay): bool;
}
