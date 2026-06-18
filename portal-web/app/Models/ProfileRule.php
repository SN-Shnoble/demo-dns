<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileRule extends Model
{
    

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $rule): void {
            if (empty($rule->id)) {
                $rule->id = 'rul_' . substr(hash('sha256', $rule->domain . microtime()), 0, 12);
            }
        });
    }

    protected $fillable = [
        'profile_id',
        'list_type',
        'match_type',
        'domain',
        'normalized_domain',
        'action',
        'category',
        'enabled',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
