<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\GeoDnsMapping;
use App\Models\Node;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * GeoDNS 演示数据：插入 4 个本地/全球节点 + 5 条 GeoDNS 映射（按国家调度）。
 * 多次执行将跳过已存在的记录，避免重复。
 */
class GeoDnsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $nodes = [
            [
                'node_code' => 'nd_local_mac',
                'name' => 'Local Mac',
                'region' => 'local',
                'country' => 'CN',
                'city' => 'Shanghai',
                'public_ipv4' => '127.0.0.1',
                'status' => 'online',
                'supported_protocols' => ['doh', 'dot', 'udp'],
            ],
            [
                'node_code' => 'nd_cn_shanghai',
                'name' => 'CN-Shanghai-1',
                'region' => 'cn-east',
                'country' => 'CN',
                'city' => 'Shanghai',
                'public_ipv4' => '10.0.0.11',
                'status' => 'online',
                'supported_protocols' => ['doh', 'dot', 'udp'],
            ],
            [
                'node_code' => 'nd_us_silicon',
                'name' => 'US-Silicon-1',
                'region' => 'us-west',
                'country' => 'US',
                'city' => 'San Jose',
                'public_ipv4' => '10.0.1.11',
                'status' => 'online',
                'supported_protocols' => ['doh', 'dot', 'udp'],
            ],
            [
                'node_code' => 'nd_eu_frankfurt',
                'name' => 'EU-Frankfurt-1',
                'region' => 'eu-central',
                'country' => 'DE',
                'city' => 'Frankfurt',
                'public_ipv4' => '10.0.2.11',
                'status' => 'online',
                'supported_protocols' => ['doh', 'dot', 'udp'],
            ],
        ];

        $nodeByCode = [];
        foreach ($nodes as $row) {
            $node = Node::query()->firstOrCreate(
                ['node_code' => $row['node_code']],
                array_merge($row, [
                    'node_name' => $row['name'],
                    'current_config_version' => 0,
                    'desired_config_version' => 1,
                ]),
            );
            $nodeByCode[$row['node_code']] = $node;
        }

        $mappings = [
            ['country' => 'CN', 'region' => 'cn-east', 'node' => 'nd_cn_shanghai', 'priority' => 10, 'weight' => 100],
            ['country' => 'HK', 'region' => 'cn-south', 'node' => 'nd_cn_shanghai', 'priority' => 20, 'weight' => 100],
            ['country' => 'TW', 'region' => 'cn-south', 'node' => 'nd_cn_shanghai', 'priority' => 20, 'weight' => 100],
            ['country' => 'US', 'region' => 'us-west', 'node' => 'nd_us_silicon', 'priority' => 10, 'weight' => 100],
            ['country' => 'DE', 'region' => 'eu-central', 'node' => 'nd_eu_frankfurt', 'priority' => 10, 'weight' => 100],
            ['country' => 'LOCAL', 'region' => 'local', 'node' => 'nd_local_mac', 'priority' => 5, 'weight' => 200],
        ];

        foreach ($mappings as $row) {
            $node = $nodeByCode[$row['node']] ?? null;
            if (! $node) {
                continue;
            }
            GeoDnsMapping::query()->updateOrCreate(
                [
                    'domain' => 'resolver.ocerlink.com',
                    'country' => $row['country'],
                    'region' => $row['region'],
                ],
                [
                    'target_node_id' => (int) $node->id,
                    'priority' => (int) $row['priority'],
                    'weight' => (int) $row['weight'],
                    'enabled' => true,
                ],
            );
        }
    }
}
