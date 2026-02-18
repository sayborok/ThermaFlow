<?php

namespace ThermaFlow;

use ThermaFlow\Contracts\QueueDriver;
use ThermaFlow\Support\SystemMonitor;

class Worker
{
    protected QueueDriver $driver;
    protected SystemMonitor $monitor;

    // Configuration
    protected int $maxJobs = 100;
    protected int $memoryLimitMB = 64;
    protected float $tempCriticalThreshold = 75.0;
    protected float $tempWarningThreshold = 60.0;

    protected int $jobsProcessed = 0;
    protected bool $shouldQuit = false;

    // Retry settings
    protected int $defaultTries = 3;
    protected int $defaultBackoff = 60;

    public function __construct(QueueDriver $driver, SystemMonitor $monitor)
    {
        $this->driver = $driver;
        $this->monitor = $monitor;
    }

    public function run(string $queue = 'default'): void
    {
        while (!$this->shouldQuit) {
            $this->checkEnvironment();

            $job = $this->driver->pop($queue);

            if ($job) {
                $this->process($job);
                $this->jobsProcessed++;
            } else {
                // If no jobs, sleep to save battery (Race-to-sleep if battery is high)
                $this->idle();
            }

            if ($this->shouldRestart()) {
                $this->stop();
            }
        }
    }

    protected function checkEnvironment(): void
    {
        $temp = $this->monitor->getCpuTemperature();

        // Thermal Throttling Algorithm
        if ($temp > $this->tempCriticalThreshold) {
            // High heat: Significant delay
            usleep(500000); // 500ms
        } elseif ($temp > $this->tempWarningThreshold) {
            // Warning heat: Dynamic delay based on temp
            $delay = (int) (($temp - $this->tempWarningThreshold) * 10000);
            usleep($delay);
        }
    }

    protected function idle(): void
    {
        $battery = $this->monitor->getBatteryLevel();
        $isCharging = $this->monitor->isCharging();

        if ($isCharging || $battery > 80) {
            // High energy: Check frequently (Race-to-sleep mindset)
            usleep(100000); // 100ms
        } elseif ($battery > 20) {
            // Normal energy: Balance
            sleep(1);
        } else {
            // Low energy: Slow down significantly to save life
            sleep(5);
        }
    }

    protected function process(object $job): void
    {
        $payload = json_decode($job->payload, true);
        $class = $payload['job'];
        $data = $payload['data'];

        try {
            if (!class_exists($class)) {
                throw new \Exception("Job class {$class} not found.");
            }

            $instance = new $class($data);
            $instance->handle();

            $this->driver->delete($job->id);
        } catch (\Throwable $e) {
            $this->handleFailure($job, $payload, $e);
        }
    }

    protected function handleFailure(object $job, array $payload, \Throwable $e): void
    {
        $class = $payload['job'];
        $maxTries = $this->defaultTries;
        $backoff = $this->defaultBackoff;

        if (class_exists($class)) {
            $ref = new \ReflectionClass($class);
            $defaultProperties = $ref->getDefaultProperties();
            $maxTries = $defaultProperties['tries'] ?? $this->defaultTries;
            $backoff = $defaultProperties['backoff'] ?? $this->defaultBackoff;
        }

        if ($job->attempts < $maxTries) {
            $this->driver->release($job->id, $backoff);
        } else {
            $this->markAsFailed($job, $e);
        }
    }

    protected function markAsFailed(object $job, \Throwable $e): void
    {
        $payload = json_decode($job->payload, true);
        $class = $payload['job'];

        if (class_exists($class)) {
            $instance = new $class($payload['data']);
            if (method_exists($instance, 'failed')) {
                $instance->failed($e);
            }
        }

        $this->driver->delete($job->id);
    }

    protected function shouldRestart(): bool
    {
        // Memory Leak Prevention
        if ($this->jobsProcessed >= $this->maxJobs) {
            return true;
        }

        $memoryUsage = memory_get_usage(true) / 1024 / 1024;
        if ($memoryUsage >= $this->memoryLimitMB) {
            return true;
        }

        return false;
    }

    public function stop(): void
    {
        $this->shouldQuit = true;
    }
}
