#!/bin/bash
cd "$(dirname "$0")"
php artisan tinker --no-interaction -q --execute="
\$h = Illuminate\Support\Facades\Hash::make('password');
Illuminate\Support\Facades\DB::table('admins')->insert([
    'id' => 'adm_main',
    'name' => 'Admin',
    'email' => 'admin@example.com',
    'password_hash' => \$h,
    'role' => 'super_admin',
    'status' => 'active',
    'is_super_admin' => true,
    'created_at' => now(),
    'updated_at' => now(),
]);
echo 'Admin created';
" 2>&1
