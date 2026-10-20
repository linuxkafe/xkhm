<?php

declare(strict_types=1);

namespace Xkhm\Service;

class ServerStatusService
{
    /**
     * Get current server status (simulated).
     */
    public function getServerStatus(): array
    {
        // In production, this would query actual server metrics
        return [
            'uptime' => $this->getUptime(),
            'load' => round(sys_getloadavg()[0] ?? 0, 2),
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'php_version' => PHP_VERSION,
            'database' => $this->checkDatabase(),
            'last_check' => date('Y-m-d H:i:s'),
        ];
    }

    private function getUptime(): string
    {
        static $startTime = null;
        if ($startTime === null) {
            $startTime = time();
        }
        $seconds = time() - $startTime;
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        return "{$days}d {$hours}h";
    }

    private function checkDatabase(): array
    {
        try {
            $pdo = \Xkhm\Database\Connection::get((new \Xkhm\Config())->getDbConfig());
            $pdo->query('SELECT 1');
            return ['status' => 'connected', 'latency' => 'OK'];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
