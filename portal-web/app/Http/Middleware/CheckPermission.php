<?php

namespace App\Http\Middleware;

use App\Domain\Auth\PermissionService;
use App\Models\Admin;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function __construct(
        private readonly PermissionService $permissionService = new PermissionService(),
    ) {
    }

    public function handle(Request $request, Closure $next, string $permission): mixed
    {
        $user = $request->user();

        if (! $this->actorHasPermission($user, $permission)) {
            abort(Response::HTTP_FORBIDDEN, 'Forbidden');
        }

        return $next($request);
    }

    /**
     * 鉴权支持 User（普通用户）和 Admin（后台账号）两类 actor。
     * 中间件只关心 PermissionService 的最终判定，因此把 actor 类型分派
     * 责任放在 service 里：User 走 role_permissions，Admin 走
     * admin_role_permissions + is_super_admin（表名自动加 config/database.php 的 `prefix`）。
     */
    private function actorHasPermission(?Authenticatable $user, string $permission): bool
    {
        if ($user === null) {
            return false;
        }

        if (! $user instanceof User && ! $user instanceof Admin) {
            return false;
        }

        return $this->permissionService->hasPermission($user, $permission);
    }
}
