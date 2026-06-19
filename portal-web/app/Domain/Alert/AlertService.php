<?php

declare(strict_types=1);

namespace App\Domain\Alert;

use App\Models\Alert;
use Illuminate\Support\Str;

final class AlertService
{
    public static function create(string $level, string $title, string $message): Alert
    {
        return Alert::create([
            'id' => 'alert_' . Str::random(16),
            'level' => $level,
            'status' => 'open',
            'title' => $title,
            'message' => $message,
        ]);
    }
}