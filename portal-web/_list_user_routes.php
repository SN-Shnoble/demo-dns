<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Route;

$routes = Route::getRoutes();
foreach ($routes as $route) {
    $uri = $route->uri();
    if (str_contains($uri, 'user/')) {
        echo $route->methods()[0] . ' /' . $uri . PHP_EOL;
    }
}
