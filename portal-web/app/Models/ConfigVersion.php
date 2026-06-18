<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConfigVersion extends Model
{
    

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'version',
        'profile_id',
        'profile_version',
        'user_id',
        'team_id',
        'status',
        'checksum',
        'config_json',
        'config_size_bytes',
        'generated_by',
        'generated_at',
        'expires_at',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $configVersion): void {
            if ($configVersion->id === null || $configVersion->id === '') {
                $configVersion->id = 'cfg_' . substr(hash('sha256', microtime(true) . random_int(1, PHP_INT_MAX)), 0, 12);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'config_json' => 'array',
            'generated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function publishTasks(): HasMany
    {
        return $this->hasMany(PublishTask::class);
    }
}
