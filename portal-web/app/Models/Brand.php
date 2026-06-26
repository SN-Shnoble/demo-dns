<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $table = 'brands';

    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'domain',
        'name',
        'category',
        'alexa_rank',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'alexa_rank' => 'integer',
        ];
    }
}
