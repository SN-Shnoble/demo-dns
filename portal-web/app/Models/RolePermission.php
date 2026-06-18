<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    protected $table = 'role_permissions';

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'role',
        'permission_code',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $rp): void {
            if (empty($rp->id)) {
                $rp->id = 'rp_' . substr(hash('sha256', $rp->role . $rp->permission_code . microtime()), 0, 12);
            }
        });
    }
}
