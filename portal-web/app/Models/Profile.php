<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profile extends Model
{
    

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $profile): void {
            if (empty($profile->id)) {
                $profile->id = 'prf_' . substr(hash('sha256', $profile->name . microtime()), 0, 12);
            }
        });
    }

    protected $fillable = [
        'user_id',
        'team_id',
        'name',
        'description',
        'status',
        'default_action',
        'block_response',
        'security_enabled',
        'security_settings',
        'adblock_enabled',
        'parental_enabled',
        'parental_settings',
        'privacy_enabled',
        'privacy_settings',
        'safe_search_enabled',
        'log_mode',
        'current_version',
        'draft_version',
        'last_published_at',
    ];

    protected function casts(): array
    {
        return [
            'security_enabled' => 'boolean',
            'security_settings' => 'array',
            'adblock_enabled' => 'boolean',
            'parental_enabled' => 'boolean',
            'parental_settings' => 'array',
            'privacy_enabled' => 'boolean',
            'privacy_settings' => 'array',
            'safe_search_enabled' => 'boolean',
            'current_version' => 'integer',
            'draft_version' => 'integer',
            'last_published_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(ProfileRule::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }
}
