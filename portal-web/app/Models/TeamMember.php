<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 软删除已迁移到数据库层移除，如需恢复请添加：use Illuminate\Database\Eloquent\SoftDeletes;

class TeamMember extends Model
{
    // SoftDeletes 已移除：成员退出直接 DELETE

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'team_id',
        'user_id',
        'role',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $member): void {
            if (empty($member->id)) {
                $member->id = 'tmb_' . substr(hash('sha256', $member->team_id . $member->user_id . microtime()), 0, 12);
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
