<?php

namespace Database\Seeders;

use App\Models\RuleCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RuleCategorySeeder extends Seeder
{
    /**
     * 预置规则分类（威胁情报 / 隐私 / 家长控制 / 自定义）
     * 与设计文档 xiufu.md 3.2 一致。
     */
    public function run(): void
    {
        $categories = [
            // threat group
            ['code' => 'malware',       'name' => '恶意软件', 'name_en' => 'Malware',           'group' => 'threat',  'icon' => 'Warning',        'color' => '#f56c6c', 'sort_order' => 10],
            ['code' => 'phishing',      'name' => '钓鱼网站', 'name_en' => 'Phishing',          'group' => 'threat',  'icon' => 'Fish',           'color' => '#e6a23c', 'sort_order' => 20],
            ['code' => 'cryptojacking', 'name' => '挖矿劫持', 'name_en' => 'Cryptojacking',     'group' => 'threat',  'icon' => 'Money',          'color' => '#b88230', 'sort_order' => 30],
            ['code' => 'dynamic_dns',   'name' => '动态DNS', 'name_en' => 'Dynamic DNS',       'group' => 'threat',  'icon' => 'Refresh',        'color' => '#909399', 'sort_order' => 40],
            ['code' => 'parked',        'name' => '停放域名', 'name_en' => 'Parked Domain',     'group' => 'threat',  'icon' => 'Box',            'color' => '#a0c4ff', 'sort_order' => 50],
            ['code' => 'typosquatting', 'name' => '误植域名', 'name_en' => 'Typosquatting',     'group' => 'threat',  'icon' => 'EditPen',        'color' => '#f78989', 'sort_order' => 60],
            ['code' => 'dga',           'name' => 'DGA域名',  'name_en' => 'DGA Domain',         'group' => 'threat',  'icon' => 'Cpu',            'color' => '#c71585', 'sort_order' => 70],
            ['code' => 'new_domain',    'name' => '新注册域名', 'name_en' => 'New Registered Domain', 'group' => 'threat', 'icon' => 'Star', 'color' => '#ff9800', 'sort_order' => 80],
            // privacy group
            ['code' => 'tracker',       'name' => '跟踪器', 'name_en' => 'Tracker',             'group' => 'privacy', 'icon' => 'View',           'color' => '#67c23a', 'sort_order' => 110],
            ['code' => 'analytics',     'name' => '分析跟踪', 'name_en' => 'Analytics',         'group' => 'privacy', 'icon' => 'DataLine',       'color' => '#409eff', 'sort_order' => 120],
            ['code' => 'telemetry',     'name' => '遥测', 'name_en' => 'Telemetry',             'group' => 'privacy', 'icon' => 'Histogram',      'color' => '#909399', 'sort_order' => 130],
            ['code' => 'ads',           'name' => '广告', 'name_en' => 'Ads',                   'group' => 'privacy', 'icon' => 'Picture',        'color' => '#f56c6c', 'sort_order' => 140],
            // family group
            ['code' => 'adult',         'name' => '成人内容', 'name_en' => 'Adult Content',     'group' => 'family',  'icon' => 'WarningFilled',  'color' => '#f56c6c', 'sort_order' => 210],
            ['code' => 'gambling',      'name' => '赌博', 'name_en' => 'Gambling',              'group' => 'family',  'icon' => 'Coin',           'color' => '#e6a23c', 'sort_order' => 220],
            ['code' => 'social',        'name' => '社交媒体', 'name_en' => 'Social Media',       'group' => 'family',  'icon' => 'ChatDotRound',   'color' => '#409eff', 'sort_order' => 230],
            ['code' => 'gaming',        'name' => '游戏', 'name_en' => 'Gaming',                'group' => 'family',  'icon' => 'VideoCamera',    'color' => '#67c23a', 'sort_order' => 240],
            // custom
            ['code' => 'custom',        'name' => '自定义', 'name_en' => 'Custom',              'group' => 'custom',  'icon' => 'Folder',         'color' => '#909399', 'sort_order' => 900],
        ];

        foreach ($categories as $row) {
            RuleCategory::updateOrCreate(
                ['code' => $row['code']],
                array_merge($row, [
                    'description' => null,
                    'parent_code' => null,
                    'enabled' => true,
                    'is_system' => true,
                ])
            );
        }
    }
}
