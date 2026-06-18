<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminRoleNavRule extends Model
{
    

    protected $fillable = ["role_id","nav_key","visible","sort_order"];

    protected function casts(): array
    {
        return [
            "visible" => "boolean",
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(AdminRole::class, "role_id");
    }

    public function nav(): BelongsTo
    {
        return $this->belongsTo(NavigationCatalog::class, "nav_key", "key");
    }
}
