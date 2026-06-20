<?php

namespace Database\Seeders;

use App\Models\Node;
use Illuminate\Database\Seeder;

class NodeSeeder extends Seeder
{
    public function run(): void
    {
        Node::updateOrCreate(
            ['node_code' => 'nd_dev_jp_01'],
            [
                'name' => 'dev-jp-01',
                'region' => 'JP',
                'country' => 'JP',
                'city' => 'Tokyo',
                'public_ipv4' => '127.0.0.1',
                'supported_protocols' => ['udp', 'tcp', 'doh'],
                'status' => 'online',
                'weight' => 100,
                'last_heartbeat_at' => now(),
            ]
        );

        Node::updateOrCreate(
            ['node_code' => 'nd_dev_cn_01'],
            [
                'name' => 'dev-cn-01',
                'region' => 'CN',
                'country' => 'CN',
                'city' => 'Shanghai',
                'public_ipv4' => '127.0.0.2',
                'supported_protocols' => ['udp', 'tcp', 'doh'],
                'status' => 'online',
                'weight' => 100,
                'last_heartbeat_at' => now(),
            ]
        );

        Node::updateOrCreate(
            ['node_code' => 'nd_dev_us_01'],
            [
                'name' => 'dev-us-01',
                'region' => 'US',
                'country' => 'US',
                'city' => 'New York',
                'public_ipv4' => '127.0.0.3',
                'supported_protocols' => ['udp', 'tcp', 'doh'],
                'status' => 'online',
                'weight' => 100,
                'last_heartbeat_at' => now(),
            ]
        );
    }
}