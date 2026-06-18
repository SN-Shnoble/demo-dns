<?php

namespace App\Models;

use Database\Factories\AdminFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * Backend administrator account. Login and session are isolated from dns_users.
 * User-facing registration and login APIs must never touch this model.
 */
class Admin extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable;

    public $incrementing = false;
    protected $keyType = "string";
    // 表名走默认 + config/database.php 的 `prefix`，不再写死 `dns_`。

    protected $fillable = [
        "name",
        "username",
        "email",
        "password_hash",
        "role",
        "status",
        "is_super_admin",
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $admin): void {
            if (empty($admin->id)) {
                $admin->id = Str::uuid()->toString();
            }
            if (empty($admin->username)) {
                $admin->username = self::buildUsernameFromEmail($admin->email);
            }
        });
    }

    protected $hidden = [
        "password_hash",
        "remember_token",
    ];

    protected function casts(): array
    {
        return [
            "is_super_admin" => "boolean",
            "last_login_at" => "datetime",
        ];
    }

    /**
     * Return the column name for the "password" filed.
     * Implements Laravel's Authenticatable contract.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function getNameAttribute(): ?string
    {
        return $this->attributes['username'] ?? null;
    }

    public function setNameAttribute(?string $value): void
    {
        $username = is_string($value) ? trim($value) : '';
        $this->attributes['username'] = $username !== '' ? $username : self::buildUsernameFromEmail((string) ($this->attributes['email'] ?? ''));
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            AdminRole::class,
            // pivot 表走默认 + config/database.php 的 `prefix`
            "admin_user_roles",
            "admin_id",
            "role_id"
        )->withPivot("assigned_by", "assigned_at");
    }

    public function assignRole(string $roleCode, ?string $assignedBy = null): void
    {
        $role = AdminRole::query()->where("code", $roleCode)->first();
        if ($role === null) {
            throw new \RuntimeException("Admin role not found: " . $roleCode);
        }
        // DB::table() 同样会拼上 config/database.php 的 `prefix`，
        // 不要再写 `dns_` 前缀，否则会被拼成 `dns_dns_admin_user_roles`。
        $exists = DB::table("admin_user_roles")
            ->where("admin_id", $this->id)
            ->where("role_id", $role->id)
            ->exists();
        if ($exists) {
            return;
        }
        DB::table("admin_user_roles")->insert([
            "admin_id"   => $this->id,
            "role_id"    => $role->id,
            "assigned_by" => $assignedBy,
            "assigned_at" => now(),
        ]);
    }

    public function hasNavKey(string $navKey): bool
    {
        if ($this->is_super_admin === true) {
            return true;
        }
        // 表名由 config/database.php 的 `prefix` 统一拼接，模型里只写裸名。
        return DB::table("admin_role_nav_rules as r")
            ->join("admin_user_roles as ur", "ur.role_id", "=", "r.role_id")
            ->where("ur.admin_id", $this->id)
            ->where("r.nav_key", $navKey)
            ->where("r.visible", true)
            ->exists();
    }

    private static function buildUsernameFromEmail(?string $email): string
    {
        $localPart = strtolower((string) Str::before((string) $email, '@'));
        $normalized = preg_replace('/[^a-z0-9._-]+/', '-', $localPart) ?: 'admin';

        return trim($normalized, '-._') !== '' ? trim($normalized, '-._') : 'admin';
    }
}
