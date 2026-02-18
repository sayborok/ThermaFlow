# ThermaFlow 🌡️🔋

![Build Status](https://github.com/sayborok/ThermaFlow/actions/workflows/tests.yml/badge.svg)

**ThermaFlow** is a resource-aware queue management library specifically designed for the **NativePHP** (Mobile) ecosystem. It optimizes background job processing to prevent device overheating and excessive battery drain.

## Key Features

-   **Thermal Throttling**: Automatically slows down job processing (using dynamic `usleep()`) when the device temperature exceeds specified thresholds.
-   **Battery-Friendly "Race-to-Sleep"**: 
    -   When battery is high or charging, it processes jobs rapidly to return the CPU to an idle state quickly.
    -   When battery is low, it increases polling intervals to conserve energy.
-   **Memory Safety**: Automatically stops and signals for a restart after a certain number of jobs or RAM usage limit to prevent memory leaks in long-running PHP processes.
-   **Job Retries & Backoff**: Built-in support for multiple attempts with configurable delays between retries.
-   **CLI Runner**: Comes with a dedicated binary to run workers easily from the terminal.
-   **SQLite Driven**: Uses a lightweight SQLite driver by default, perfect for mobile environments without external dependencies like Redis.
-   **Native Bridge Integration**: Designed to hook into NativePHP's hardware bridges for temperature and battery monitoring.

## Installation

```bash
composer require sayborok/thermaflow
```

## Basic Usage

### 1. Define a Job

Extend the base `Job` class to get retry and failure handling features.

```php
namespace App\Jobs;

use ThermaFlow\Job;

class SendNotification extends Job {
    public int $tries = 5;       // Override default tries
    public int $backoff = 30;    // Wait 30s between retries

    public function __construct(protected array $data) {}

    public function handle() {
        // Your logic here
    }

    public function failed(\Throwable $e) {
        // Log or notify on final failure
    }
}
```

### 2. Push to Queue

```php
use ThermaFlow\Queue;
use ThermaFlow\Drivers\SQLiteDriver;

$driver = new SQLiteDriver(storage_path('queue.sqlite'));
$queue = new Queue($driver);

$queue->push(\App\Jobs\SendNotification::class, ['user_id' => 123]);
```

### 3. Run the Worker

You can use the built-in CLI runner:

```bash
php bin/thermaflow --queue=default
```

Or programmatically:

```php
use ThermaFlow\Worker;
use ThermaFlow\Drivers\SQLiteDriver;
use ThermaFlow\Support\SystemMonitor;

$driver = new SQLiteDriver(storage_path('queue.sqlite'));
$monitor = new SystemMonitor();
$worker = new Worker($driver, $monitor);

$worker->run('default');
```

## How It Works: Thermal Throttling Algorithm

ThermaFlow monitors the CPU temperature before each job execution:
1.  **Stage 1 (< 60°C)**: Full speed processing.
2.  **Stage 2 (60°C - 75°C)**: Dynamic `usleep()` injected based on the delta from 60°C.
3.  **Stage 3 (> 75°C)**: Strict throttling with 500ms mandatory delay between jobs.

## License
MIT
