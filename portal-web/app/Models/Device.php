<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $device): void {
            if (empty($device->id)) {
                $device->id = 'dev_' . substr(hash('sha256', $device->name . microtime()), 0, 12);
            }
        });
    }

    protected $fillable = [
        'user_id',
        'profile_id',
        'name',
        'device_type',
        'device_id',
        'public_ip',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
