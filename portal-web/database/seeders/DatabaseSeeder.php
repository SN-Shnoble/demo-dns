<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Admin;
use App\Domain\Auth\PermissionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin account for management backend - super_admin role
        Admin::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'id' => 'adm_01H00000000000000000000000001',
                'username' => 'admin',
                'email' => 'admin@example.com',
                'password_hash' => Hash::make('123456'),
                'role' => 'super_admin',
                'status' => 'active',
                'is_super_admin' => true,
            ]
        );

        // Member account for user member center
        User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'id' => 'usr_01H00000000000000000000000001',
                'username' => 'user',
                'email' => 'user@example.com',
                'password' => Hash::make('123456'),
                'role' => 'member',
                'status' => 'active',
                'timezone' => 'Asia/Shanghai',
                'locale' => 'zh-CN',
            ]
        );

        // Seed default permissions and role mappings
        PermissionService::seedDefaults();
    }
}
