<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NodeToken extends Model
{
    

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'node_id',
        'token_hash',
        'hmac_key_hash',
        'hmac_secret_encrypted',
        'name',
        'last_used_at',
        'expires_at',
        'revoked_at',
        'created_at',
    ];

    public $timestamps = false;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $token): void {
            if ($token->id === null || $token->id === '') {
                $token->id = 'ntk_' . substr(hash('sha256', microtime(true) . random_int(1, PHP_INT_MAX)), 0, 12);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
