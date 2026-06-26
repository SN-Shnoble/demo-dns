<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RuleCategory extends Model
{
    protected $table = 'rule_categories';

    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'code',
        'name',
        'name_en',
        'description',
        'icon',
        'color',
        'parent_code',
        'group',
        'enabled',
        'is_system',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'is_system' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
