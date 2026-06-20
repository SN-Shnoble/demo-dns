<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Admin;
use Laravel\Sanctum\PersonalAccessToken;

$admin = Admin::orderBy('admin_id')->first();
echo "admin_id=" . $admin->admin_id . " email=" . $admin->email . PHP_EOL;
$token = $admin->createToken('debug-cli')->plainTextToken;
file_put_contents('/tmp/admin_token.txt', $token);
echo "token=" . $token . PHP_EOL;
