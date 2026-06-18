<?php

declare(strict_types=1);

namespace App\Domain\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class HealthCheckService
{
    private const PROBE_TIMEOUT_SECONDS = 1.5;

    /**
     * @return array{
     *     db_connected:bool,
     *     redis_connected:bool,
     *     redis_required:bool,
     *     clickhouse_connected:bool,
     *     clickhouse_required:bool,
     *     db_error?:string,
     *     redis_error?:string,
     *     clickhouse_error?:string
     * }
     */
    public function probe(): array
    {
        $result = [
            'db_connected' => $this->checkDb(),
            'redis_connected' => $this->checkRedis(),
            'redis_required' => $this->isDriver('redis', ['cache', 'queue', 'session']),
            'clickhouse_connected' => $this->checkClickHouse(),
            'clickhouse_required' => $this->isClickHouseConfigured(),
        ];

        if (! $result['db_connected']) {
            $result['db_error'] = $this->lastError('db');
        }
        if (! $result['redis_connected']) {
            $result['redis_error'] = $this->lastError('redis');
        }
        if (! $result['clickhouse_connected']) {
            $result['clickhouse_error'] = $this->lastError('clickhouse');
        }
        return $result;
    }

    private function checkDb(): bool
    {
        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();

            // SELECT 1 works on every supported driver (mysql, pgsql).
            $value = $connection->select('select 1 as health_check');
            return is_array($value) && count($value) > 0 && (int) ($value[0]->health_check ?? 0) === 1;
        } catch (Throwable) {
            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            $pong = Redis::connection()->ping();
            // Predis returns "PONG" string; phpredis returns true (or "+PONG").
            return $pong === true || $pong === 'PONG' || $pong === '+PONG';
        } catch (Throwable) {
            return false;
        }
    }

    private function checkClickHouse(): bool
    {
        $host = (string) config('clickhouse.host', '');
        if ($host === '') {
            return false;
        }
        try {
            $client = new \App\Infrastructure\ClickHouse\ClickHouseClient();
            return $client->ping();
        } catch (Throwable) {
            return false;
        }
    }

    private function isClickHouseConfigured(): bool
    {
        return (string) config('clickhouse.host', '') !== '';
    }

    /**
     * @param  string[]  $configKeys
     */
    private function isDriver(string $driver, array $configKeys): bool
    {
        foreach ($configKeys as $key) {
            $value = config($key . '.default') ?: config($key . '.driver');
            if ($value === $driver) {
                return true;
            }
        }
        return false;
    }

    private function lastError(string $which): ?string
    {
        try {
            $bag = app('health-probe-errors');
        } catch (\Illuminate\Contracts\Container\BindingResolutionException) {
            $bag = null;
        }
        return is_array($bag) ? ($bag[$which] ?? null) : null;
    }
}
