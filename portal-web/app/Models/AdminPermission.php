<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AdminPermission extends Model
{
    

    protected $fillable = ["code","resource","action","description"];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            AdminRole::class,
            // pivot 表走默认 + config/database.php 的 `prefix`
            "admin_role_permissions",
            "permission_id",
            "role_id"
        );
    }
}
