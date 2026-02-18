<?php

namespace ThermaFlow;

/**
 * Base Job class with retry support.
 */
abstract class Job
{
    public int $tries = 3;
    public int $backoff = 60; // seconds

    abstract public function handle(): void;

    public function failed(\Throwable $exception): void
    {
        // Optional hook for the user to handle final failure
    }
}
