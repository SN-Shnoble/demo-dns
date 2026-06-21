<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Models\SystemConfig;
use Illuminate\Http\JsonResponse;

/**
 * PublicConfigController
 *
 * 提供无需认证的公开配置查询（如 DNS 域名），供会员端使用。
 */
final class PublicConfigController
{
    public function dnsConfig(): JsonResponse
    {
        $dnsDomain = 'dns.ocerlink.com';

        $dns = SystemConfig::query()->where('config_key', 'dns')->first();
        if ($dns !== null && $dns->value) {
            $decoded = is_string($dns->value) ? json_decode($dns->value, true) : $dns->value;
            if (is_array($decoded) && ! empty($decoded['dns_domain'])) {
                $dnsDomain = (string) $decoded['dns_domain'];
            }
        }

        if ($dnsDomain === 'dns.ocerlink.com') {
            $basic = SystemConfig::query()->where('config_key', 'basic')->first();
            if ($basic !== null && $basic->value) {
                $decoded = is_string($basic->value) ? json_decode($basic->value, true) : $basic->value;
                if (is_array($decoded) && ! empty($decoded['dns_domain'])) {
                    $dnsDomain = (string) $decoded['dns_domain'];
                }
            }
        }

        return response()->json([
            'data' => [
                'dns_domain' => $dnsDomain,
            ],
        ]);
    }
}
