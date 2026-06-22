<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Node;

use App\Models\Node;

/**
 * DNS Resolver 节点注册端点（2026-06-22 统一改造后）
 *
 * 继承 BaseNodeRegisterController，限定 node_type='dns-resolver'。
 * URL: POST /api/v1/node/dns-resolver/register
 */
final class NodeRegisterController extends BaseNodeRegisterController
{
    /**
     * 数据库里历史上把 dns-resolver 节点存为 node_type='resolver'，
     * 2026-06-22 改造后部分新流程会写成 'dns-resolver'。
     * 两种值都允许通过校验。
     */
    protected function expectedNodeType(): ?string
    {
        return null;
    }

    /**
     * 自定义类型校验:同时允许 'resolver' 和 'dns-resolver'。
     */
    protected function checkNodeType(Node $node): bool
    {
        return in_array($node->node_type, ['resolver', 'dns-resolver'], true);
    }
}
