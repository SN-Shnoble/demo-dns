<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$h = Illuminate\Support\Facades\Hash::make('password');
Illuminate\Support\Facades\DB::table('admins')->insert([
    'id' => 'adm_main',
    'username' => 'Admin',
    'email' => 'admin@example.com',
    'password_hash' => $h,
    'role' => 'super_admin',
    'status' => 'active',
    'is_super_admin' => true,
    'created_at' => now(),
    'updated_at' => now(),
]);
echo "Admin created: admin@example.com / password\n";
