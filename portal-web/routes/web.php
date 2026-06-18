<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function (): array {
    return [
        'app' => 'portal-web',
        'status' => 'ok',
    ];
});
