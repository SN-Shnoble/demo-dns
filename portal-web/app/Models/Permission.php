<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $table = 'permissions';

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'description',
        'group_name',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $permission): void {
            if (empty($permission->id)) {
                $permission->id = 'perm_' . substr(hash('sha256', $permission->code . microtime()), 0, 12);
            }
        });
    }
}
