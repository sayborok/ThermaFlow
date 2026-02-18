<?php

namespace ThermaFlow\Support;

class SystemMonitor
{
    /**
     * Get the current CPU temperature.
     * In a real NativePHP environment, this would call the bridge.
     * For now, we provide a placeholder that can be hooked.
     */
    public function getCpuTemperature(): float
    {
        // Placeholder: integration with NativePHP\Bridge\RemoteProcess or similar
        // For development/mocking purposes:
        return (float) (getenv('THERMAFLOW_MOCK_TEMP') ?: 45.0);
    }

    /**
     * Get the battery percentage.
     */
    public function getBatteryLevel(): int
    {
        // Placeholder: integration with NativePHP Power bridge
        return (int) (getenv('THERMAFLOW_MOCK_BATTERY') ?: 80);
    }

    /**
     * Check if the device is plugged in.
     */
    public function isCharging(): bool
    {
        return (bool) (getenv('THERMAFLOW_MOCK_CHARGING') ?: true);
    }

    /**
     * Get system load average (1 min).
     */
    public function getSystemLoad(): float
    {
        $load = sys_getloadavg();
        return (float) $load[0];
    }
}
