<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskExecution extends Model
{
    // 表名走默认 snake_case 复数 + config/database.php 的 `prefix`。
    // 不再在模型里写死 `dns_` 前缀；想要改前缀只需调整 DB_TABLE_PREFIX。

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'publish_task_id',
        'node_id',
        'config_version',
        'status',
        'checksum',
        'error_code',
        'error_message',
        'pulled_at',
        'applied_at',
        'last_seen_at',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $execution): void {
            if ($execution->id === null || $execution->id === '') {
                $execution->id = 'exe_' . substr(hash('sha256', microtime(true) . random_int(1, PHP_INT_MAX)), 0, 12);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'pulled_at' => 'datetime',
            'applied_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function publishTask(): BelongsTo
    {
        return $this->belongsTo(PublishTask::class);
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
