<?php

namespace App\Domain\Auth;

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PermissionService
{
    /**
     * Check if the given actor (User or Admin) has a specific permission.
     *
     * Admin actors are dispatched to {@see self::hasAdminPermission()} so the
     * User permission tables are never touched on admin API calls. Super
     * admins short-circuit and always pass.
     */
    public function hasPermission(User|Admin $user, string $permissionCode): bool
    {
        if ($user instanceof Admin) {
            return $this->hasAdminPermission($user, $permissionCode);
        }

        if ($user->role === 'admin') {
            return true;
        }

        return RolePermission::where('role', $user->role)
            ->where('permission_code', $permissionCode)
            ->exists();
    }

    /**
     * Check if a user has a specific role.
     */
    public function hasRole(User $user, string $role): bool
    {
        return $user->role === $role;
    }

    /**
     * Assign a role to a user.
     */
    public function assignRole(User $user, string $role): void
    {
        $user->update(['role' => $role]);
    }

    /**
     * Get all permissions for a user's role.
     *
     * @return array<int, string>
     */
    public function getUserPermissions(User $user): array
    {
        if ($user->role === 'admin') {
            return Permission::pluck('code')->toArray();
        }

        return RolePermission::where('role', $user->role)
            ->pluck('permission_code')
            ->toArray();
    }

    /**
     * Check if an admin has a specific admin permission code.
     *
     * Looks up the permission through the admin's assigned roles. Super
     * admins always pass. Inactive admins are denied regardless of role.
     */
    public function hasAdminPermission(Admin $admin, string $permissionCode): bool
    {
        if (! $this->isActiveAdmin($admin)) {
            return false;
        }

        if ($admin->is_super_admin === true) {
            return true;
        }

        // pivot 表名要拼上 config/database.php 的 `prefix`，不能写死 `dns_`。
        $pivot = DB::getTablePrefix() . 'admin_user_roles';
        $permissionTable = DB::getTablePrefix() . 'admin_permissions';

        return AdminRole::query()
            ->whereHas('admins', function ($query) use ($admin, $pivot): void {
                $query->where("{$pivot}.admin_id", $admin->id);
            })
            ->whereHas('permissions', function ($query) use ($permissionCode, $permissionTable): void {
                $query->where("{$permissionTable}.code", $permissionCode);
            })
            ->exists();
    }

    /**
     * Get all permission codes assigned to an admin via their roles.
     *
     * @return array<int, string>
     */
    public function getAdminPermissions(Admin $admin): array
    {
        if (! $this->isActiveAdmin($admin)) {
            return [];
        }

        if ($admin->is_super_admin === true) {
            return \App\Models\AdminPermission::pluck('code')->toArray();
        }

        $pivot = DB::getTablePrefix() . 'admin_user_roles';

        return AdminRole::query()
            ->whereHas('admins', function ($query) use ($admin, $pivot): void {
                $query->where("{$pivot}.admin_id", $admin->id);
            })
            ->with('permissions:id,code')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->unique()
            ->values()
            ->all();
    }

    private function isActiveAdmin(Admin $admin): bool
    {
        return $admin->status === 'active';
    }

    /**
     * Seed default permissions and role-permission mappings.
     */
    public static function seedDefaults(): void
    {
        $permissions = [
            ['code' => 'admin.access', 'name' => 'Admin Access', 'description' => 'Access admin panel', 'group_name' => 'admin'],
            ['code' => 'users.manage', 'name' => 'Manage Users', 'description' => 'View and manage users', 'group_name' => 'admin'],
            ['code' => 'teams.manage', 'name' => 'Manage Teams', 'description' => 'View and manage all teams', 'group_name' => 'admin'],
            ['code' => 'audit.view', 'name' => 'View Audit Logs', 'description' => 'View audit logs', 'group_name' => 'admin'],
            ['code' => 'plans.manage', 'name' => 'Manage Plans', 'description' => 'CRUD plans and prices', 'group_name' => 'admin'],
            ['code' => 'orders.view', 'name' => 'View Orders', 'description' => 'View orders and invoices', 'group_name' => 'admin'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['code' => $perm['code']], $perm);
        }

        $adminPermissions = Permission::pluck('code')->toArray();
        foreach ($adminPermissions as $code) {
            RolePermission::firstOrCreate([
                'role' => 'admin',
                'permission_code' => $code,
            ]);
        }
    }
}
