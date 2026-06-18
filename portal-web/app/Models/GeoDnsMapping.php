<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeoDnsMapping extends Model
{
    protected $table = 'geo_dns_mappings';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'country',
        'region',
        'node_id',
        'priority',
        'weight',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $mapping): void {
            if (empty($mapping->id)) {
                $mapping->id = 'geo_' . substr(hash('sha256', microtime(true) . random_int(1, PHP_INT_MAX)), 0, 12);
            }
        });
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
