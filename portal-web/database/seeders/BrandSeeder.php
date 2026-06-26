<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    /**
     * 预置品牌（Top 100 主流品牌 + 金融/电商/科技/社交）
     * 来源：人工整理的常见 typo squatting 目标品牌
     * Resolver 检测时使用此表。
     */
    public function run(): void
    {
        $brands = [
            // Tech
            ['domain' => 'google.com',     'name' => 'Google',     'category' => 'tech',      'alexa_rank' => 1],
            ['domain' => 'youtube.com',    'name' => 'YouTube',    'category' => 'tech',      'alexa_rank' => 2],
            ['domain' => 'facebook.com',   'name' => 'Facebook',   'category' => 'social',    'alexa_rank' => 3],
            ['domain' => 'amazon.com',     'name' => 'Amazon',     'category' => 'ecommerce', 'alexa_rank' => 4],
            ['domain' => 'wikipedia.org',  'name' => 'Wikipedia',  'category' => 'tech',      'alexa_rank' => 5],
            ['domain' => 'twitter.com',    'name' => 'Twitter',    'category' => 'social',    'alexa_rank' => 6],
            ['domain' => 'x.com',          'name' => 'X',          'category' => 'social',    'alexa_rank' => 7],
            ['domain' => 'instagram.com',  'name' => 'Instagram',  'category' => 'social',    'alexa_rank' => 8],
            ['domain' => 'reddit.com',     'name' => 'Reddit',     'category' => 'social',    'alexa_rank' => 9],
            ['domain' => 'linkedin.com',   'name' => 'LinkedIn',   'category' => 'social',    'alexa_rank' => 10],
            // Big Tech
            ['domain' => 'apple.com',      'name' => 'Apple',      'category' => 'tech',      'alexa_rank' => 11],
            ['domain' => 'microsoft.com',  'name' => 'Microsoft',  'category' => 'tech',      'alexa_rank' => 12],
            ['domain' => 'github.com',     'name' => 'GitHub',     'category' => 'tech',      'alexa_rank' => 50],
            ['domain' => 'gitlab.com',     'name' => 'GitLab',     'category' => 'tech',      'alexa_rank' => 200],
            ['domain' => 'docker.com',     'name' => 'Docker',     'category' => 'tech',      'alexa_rank' => 300],
            ['domain' => 'kubernetes.io',  'name' => 'Kubernetes', 'category' => 'tech',      'alexa_rank' => 400],
            ['domain' => 'cloudflare.com', 'name' => 'Cloudflare', 'category' => 'tech',      'alexa_rank' => 100],
            ['domain' => 'amazonaws.com',  'name' => 'AWS',        'category' => 'tech',      'alexa_rank' => 150],
            ['domain' => 'azure.com',      'name' => 'Azure',      'category' => 'tech',      'alexa_rank' => 250],
            // Finance
            ['domain' => 'paypal.com',     'name' => 'PayPal',     'category' => 'finance',   'alexa_rank' => 20],
            ['domain' => 'stripe.com',     'name' => 'Stripe',     'category' => 'finance',   'alexa_rank' => 500],
            ['domain' => 'visa.com',       'name' => 'Visa',       'category' => 'finance',   'alexa_rank' => 800],
            ['domain' => 'mastercard.com', 'name' => 'Mastercard', 'category' => 'finance',   'alexa_rank' => 900],
            ['domain' => 'chase.com',      'name' => 'Chase',      'category' => 'finance',   'alexa_rank' => 1000],
            // Chinese Tech
            ['domain' => 'baidu.com',      'name' => 'Baidu',      'category' => 'tech',      'alexa_rank' => 13],
            ['domain' => 'qq.com',         'name' => 'Tencent QQ', 'category' => 'social',    'alexa_rank' => 14],
            ['domain' => 'weibo.com',      'name' => 'Weibo',      'category' => 'social',    'alexa_rank' => 30],
            ['domain' => 'taobao.com',     'name' => 'Taobao',     'category' => 'ecommerce', 'alexa_rank' => 15],
            ['domain' => 'tmall.com',      'name' => 'Tmall',      'category' => 'ecommerce', 'alexa_rank' => 16],
            ['domain' => 'jd.com',         'name' => 'JD.com',     'category' => 'ecommerce', 'alexa_rank' => 17],
            ['domain' => 'alipay.com',     'name' => 'Alipay',     'category' => 'finance',   'alexa_rank' => 60],
            ['domain' => 'bilibili.com',   'name' => 'Bilibili',   'category' => 'social',    'alexa_rank' => 70],
            ['domain' => 'douyin.com',     'name' => 'Douyin',     'category' => 'social',    'alexa_rank' => 80],
            ['domain' => 'wechat.com',     'name' => 'WeChat',     'category' => 'social',    'alexa_rank' => 90],
            // Ecommerce
            ['domain' => 'ebay.com',       'name' => 'eBay',       'category' => 'ecommerce', 'alexa_rank' => 25],
            ['domain' => 'aliexpress.com', 'name' => 'AliExpress', 'category' => 'ecommerce', 'alexa_rank' => 35],
            ['domain' => 'walmart.com',    'name' => 'Walmart',    'category' => 'ecommerce', 'alexa_rank' => 40],
            ['domain' => 'shopify.com',    'name' => 'Shopify',    'category' => 'ecommerce', 'alexa_rank' => 200],
            // Streaming
            ['domain' => 'netflix.com',    'name' => 'Netflix',    'category' => 'social',    'alexa_rank' => 18],
            ['domain' => 'spotify.com',    'name' => 'Spotify',    'category' => 'social',    'alexa_rank' => 100],
            ['domain' => 'twitch.tv',      'name' => 'Twitch',     'category' => 'social',    'alexa_rank' => 120],
            ['domain' => 'disneyplus.com', 'name' => 'Disney+',    'category' => 'social',    'alexa_rank' => 250],
            ['domain' => 'hulu.com',       'name' => 'Hulu',       'category' => 'social',    'alexa_rank' => 400],
            // Productivity
            ['domain' => 'office.com',     'name' => 'Microsoft Office', 'category' => 'tech', 'alexa_rank' => 80],
            ['domain' => 'zoom.us',        'name' => 'Zoom',       'category' => 'tech',      'alexa_rank' => 110],
            ['domain' => 'slack.com',      'name' => 'Slack',      'category' => 'tech',      'alexa_rank' => 300],
            ['domain' => 'notion.so',      'name' => 'Notion',     'category' => 'tech',      'alexa_rank' => 500],
            ['domain' => 'dropbox.com',    'name' => 'Dropbox',    'category' => 'tech',      'alexa_rank' => 600],
            ['domain' => 'adobe.com',      'name' => 'Adobe',      'category' => 'tech',      'alexa_rank' => 130],
        ];

        foreach ($brands as $row) {
            Brand::updateOrCreate(
                ['domain' => $row['domain']],
                array_merge($row, ['enabled' => true])
            );
        }
    }
}
