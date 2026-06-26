<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RuleItem extends Model
{
    protected $table = 'rule_items';

    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'rule_source_id',
        'domain',
        'category',
        'action',
        'tag',
        'source_domain',
        'expires_at',
        'confidence',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}
