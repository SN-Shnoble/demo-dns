<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileVersion extends Model
{
    

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $version): void {
            if (empty($version->id)) {
                $version->id = 'pvr_' . substr(hash('sha256', microtime()), 0, 12);
            }
        });
    }

    protected $fillable = [
        'profile_id',
        'version',
        'status',
        'checksum',
        'config_json',
        'rule_count',
        'message',
        'published_by',
        'external_publish_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'config_json' => 'array',
            'rule_count' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
