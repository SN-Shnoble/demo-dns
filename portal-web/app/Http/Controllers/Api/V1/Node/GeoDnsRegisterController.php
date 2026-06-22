<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Node;

/**
 * GeoDNS 节点注册端点（2026-06-22 统一改造后）
 *
 * 继承 BaseNodeRegisterController，限定 node_type='geodns'。
 * URL: POST /api/v1/node/geodns/register
 */
final class GeoDnsRegisterController extends BaseNodeRegisterController
{
    protected function expectedNodeType(): ?string
    {
        return 'geodns';
    }
}
