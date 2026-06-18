<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAuditLog extends Model
{
    // 表名走 Laravel 默认（snake_case 复数） + config/database.php 的 prefix
    // 不要硬编码表名，否则前缀配置会失效

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'actor_id',
        'actor_username',
        'action',
        'target_type',
        'target_id',
        'ip',
        'user_agent',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $log): void {
            if (empty($log->id)) {
                $log->id = 'alog_' . substr(hash('sha256', microtime(true) . random_int(1, PHP_INT_MAX)), 0, 12);
            }
            if ($log->created_at === null) {
                $log->created_at = now();
            }
        });
    }

    public static function record(string $action, ?string $targetType = null, ?string $targetId = null, array $payload = [], ?string $actorId = null, ?string $actorUsername = null, ?string $ip = null, ?string $userAgent = null): self
    {
        // 如果没有提供 actorUsername 但有 actorId，自动从 Admin 表查询
        if ($actorUsername === null && $actorId !== null) {
            $admin = \App\Models\Admin::find($actorId);
            $actorUsername = $admin?->username;
        }

        return self::create([
            'actor_id' => $actorId,
            'actor_username' => $actorUsername,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'payload' => $payload ?: null,
        ]);
    }
}
