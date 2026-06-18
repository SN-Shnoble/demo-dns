<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | ClickHouse HTTP reader
    |--------------------------------------------------------------------------
    |
    | portal-web (which now absorbs the dns-console-web control plane) uses
    | ClickHouse for two read paths:
    |   1. Member analytics (top visited / blocked, query volume per day)
    |   2. Admin stats + geodns health view (cross-node rollups, online/offline)
    |
    | All inserts stay on the dns-resolver side (Go binary → native TCP);
    | portal-web is read-only. An unreachable ClickHouse is therefore a
    | degraded-but-online state — the read paths will return empty data
    | and the call sites surface a 502 to the user instead of caching
    | stale numbers.
    |
    | The host / port / database / credentials can be overridden per
    | environment. CLICKHOUSE_PASSWORD_FILE points at a Docker secret so
    | we never read a password from a process-visible env var. When
    | CLICKHOUSE_ENABLED is false the client is hard-disabled and the
    | in-process analytics path returns 0 rows (with a log line).
    */
    'host'        => env('CLICKHOUSE_HOST', ''),
    'port'        => (int) env('CLICKHOUSE_PORT', 8123),
    'database'    => env('CLICKHOUSE_DATABASE', 'ocer_dns'),
    'username'    => env('CLICKHOUSE_USER', 'ocer'),
    'password'    => env('CLICKHOUSE_PASSWORD', ''),
    'password_file' => env('CLICKHOUSE_PASSWORD_FILE', ''),
    'timeout_seconds'        => (float) env('CLICKHOUSE_TIMEOUT', 1.5),
    'connect_timeout_seconds' => (float) env('CLICKHOUSE_CONNECT_TIMEOUT', 1.0),
    'enabled'     => (bool) env('CLICKHOUSE_ENABLED', true),
];
