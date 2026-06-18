<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueryLogEntry extends Model
{
    

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'ingest_batch_id',
        'node_id',
        'user_id',
        'profile_id',
        'device_id',
        'query_name',
        'query_type',
        'action',
        'reason',
        'category',
        'client_ip',
        'rcode',
        'latency_ms',
        'queried_at',
        'created_at',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $entry): void {
            if ($entry->id === null || $entry->id === '') {
                $entry->id = 'qle_' . substr(hash('sha256', microtime(true) . random_int(1, PHP_INT_MAX)), 0, 12);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'rcode' => 'integer',
            'latency_ms' => 'integer',
            'queried_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(QueryLogIngestBatch::class, 'ingest_batch_id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
