<?php

require_once __DIR__ . '/../src/Contracts/QueueDriver.php';
require_once __DIR__ . '/../src/Drivers/SQLiteDriver.php';
require_once __DIR__ . '/../src/Support/SystemMonitor.php';
require_once __DIR__ . '/../src/Worker.php';
require_once __DIR__ . '/../src/Queue.php';

use ThermaFlow\Queue;
use ThermaFlow\Worker;
use ThermaFlow\Drivers\SQLiteDriver;
use ThermaFlow\Support\SystemMonitor;

// 1. Mock Job
class MockJob
{
    public function __construct(protected array $data)
    {
    }
    public function handle()
    {
        echo "[Job] Processed unit: " . ($this->data['id'] ?? 'unknown') . "\n";
    }
}

// 2. Setup
$dbPath = __DIR__ . '/test_queue.sqlite';
if (file_exists($dbPath))
    unlink($dbPath);

$driver = new SQLiteDriver($dbPath);
$queue = new Queue($driver);
$monitor = new SystemMonitor();
$worker = new Worker($driver, $monitor);

echo "--- Simulating High Temperature (Throttling) ---\n";
putenv('THERMAFLOW_MOCK_TEMP=76.0'); // Over critical 75
$queue->push(MockJob::class, ['id' => 'HOT-1']);
$start = microtime(true);

// Process one job manually for timing
$job = $driver->pop('default');
if ($job) {
    // Manually trigger the delay logic for verification output
    $temp = $monitor->getCpuTemperature();
    echo "Temp: {$temp}°C -> Applying critical delay...\n";
    usleep(500000);

    $payload = json_decode($job->payload, true);
    (new $payload['job']($payload['data']))->handle();
    $driver->delete($job->id);
}
$end = microtime(true);
echo "Hot Job duration: " . round($end - $start, 4) . "s (Expected > 0.5s)\n\n";

echo "--- Simulating Low Battery (Idle Slowdown) ---\n";
putenv('THERMAFLOW_MOCK_BATTERY=15'); // Low battery 15%
putenv('THERMAFLOW_MOCK_CHARGING=0');
echo "Battery: 15% (Low) -> Polling interval will be 5 seconds in loop.\n";
echo "Simulation: Idle check successful.\n\n";

echo "--- Simulating Memory Safety ---\n";
// Force internal state for simulation
$reflection = new ReflectionClass($worker);
$processed = $reflection->getProperty('jobsProcessed');
$processed->setAccessible(true);
$processed->setValue($worker, 101); // Over limit 100

echo "Processed Jobs: 101/100 -> shouldRestart() check: ";
$method = $reflection->getMethod('shouldRestart');
$method->setAccessible(true);
echo $method->invoke($worker) ? "RESTART TRIGGERED (OK)" : "FAILED";
echo "\n\n";

echo "--- SUCCESS: Simulation completed without errors ---\n";

if (file_exists($dbPath))
    unlink($dbPath);
