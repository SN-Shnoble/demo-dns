<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NavigationCatalog extends Model
{
    protected $primaryKey = "key";
    public $incrementing = false;
    protected $keyType = "string";
    

    protected $fillable = ["key","parent_key","title","path","icon","sort_order","is_active","permission_code"];

    protected function casts(): array
    {
        return [
            "is_active" => "boolean",
            "sort_order" => "integer",
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, "parent_key", "key");
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, "parent_key", "key");
    }
}
