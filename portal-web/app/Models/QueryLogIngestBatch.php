<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueryLogIngestBatch extends Model
{
    

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'batch_id',
        'node_id',
        'item_count',
        'content_sha256',
        'usage_exported_at',
        'status',
        'error_message',
        'received_at',
        'written_at',
    ];

    public $timestamps = false;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $batch): void {
            if ($batch->id === null || $batch->id === '') {
                $batch->id = 'qlb_' . substr(hash('sha256', microtime(true) . random_int(1, PHP_INT_MAX)), 0, 12);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'usage_exported_at' => 'datetime',
            'received_at' => 'datetime',
            'written_at' => 'datetime',
        ];
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
