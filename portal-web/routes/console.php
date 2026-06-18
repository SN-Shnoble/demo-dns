<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('portal:about', function (): void {
    $this->comment('portal-web command scaffold ready');
});
