<?php

namespace Tests\Feature;

use App\Models\ProfileVersion;
use App\Models\Device;
use App\Models\Node;
use App\Models\QueryLogEntry;
use App\Models\QueryLogIngestBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MemberWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_workspace_endpoints_persist_primary_profile_settings(): void
    {
        $user = $this->createUser('workspace1@example.com', 'password123');

        Sanctum::actingAs($user, [], 'api');

        $this->putJson('/api/v1/member/security', [
            'enabled' => true,
            'block_malware' => true,
            'block_phishing' => false,
            'block_command_and_control' => true,
            'block_cryptojacking' => false,
        ])->assertOk()->assertJsonPath('data.block_phishing', false);

        $this->putJson('/api/v1/member/privacy', [
            'enabled' => true,
            'block_trackers' => true,
            'block_analytics' => true,
            'block_telemetry' => false,
            'anonymize_client_ip' => true,
            'log_mode' => 'blocked_only',
        ])->assertOk()->assertJsonPath('data.log_mode', 'blocked_only');

        $this->putJson('/api/v1/member/parental', [
            'enabled' => true,
            'block_adult_content' => true,
            'safe_search' => true,
            'youtube_restricted_mode' => false,
            'block_gambling_basic' => true,
        ])->assertOk()->assertJsonPath('data.safe_search', true);

        $this->putJson('/api/v1/member/settings', [
            'locale' => 'zh-CN',
            'timezone' => 'Asia/Shanghai',
            'profile_name' => 'Family Profile',
            'default_action' => 'allow',
            'block_response' => 'zero_ip',
        ])->assertOk()->assertJsonPath('data.profile_name', 'Family Profile');

        $this->getJson('/api/v1/member/settings')
            ->assertOk()
            ->assertJsonPath('data.locale', 'zh-CN')
            ->assertJsonPath('data.profile_name', 'Family Profile');
    }

    public function test_allowlist_and_denylist_endpoints_work_against_primary_profile(): void
    {
        $user = $this->createUser('workspace2@example.com');
        Sanctum::actingAs($user, [], 'api');

        $allow = $this->postJson('/api/v1/member/allowlist', [
            'domain' => 'openai.com',
            'match_type' => 'exact',
        ])->assertCreated();

        $deny = $this->postJson('/api/v1/member/denylist', [
            'domain' => 'ads.example.com',
            'match_type' => 'suffix',
        ])->assertCreated();

        $this->getJson('/api/v1/member/allowlist')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/member/denylist')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->deleteJson('/api/v1/member/allowlist/' . $allow->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->deleteJson('/api/v1/member/denylist/' . $deny->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.deleted', true);
    }

    public function test_publish_uses_persisted_profile_state_and_creates_profile_version(): void
    {
        $user = $this->createUser('workspace3@example.com');
        Sanctum::actingAs($user, [], 'api');

        $this->putJson('/api/v1/member/settings', [
            'locale' => 'en',
            'timezone' => 'UTC',
            'profile_name' => 'Home',
            'default_action' => 'block',
            'block_response' => 'refused',
        ])->assertOk();

        $this->putJson('/api/v1/member/security', [
            'enabled' => true,
            'block_malware' => true,
            'block_phishing' => true,
            'block_command_and_control' => true,
            'block_cryptojacking' => true,
        ])->assertOk();

        $ruleResponse = $this->postJson('/api/v1/member/denylist', [
            'domain' => 'tracker.example.com',
            'match_type' => 'exact',
        ])->assertCreated();

        $profileId = $this->getJson('/api/v1/member/profiles')->json('data.0.id');
        Device::create([
            'user_id' => $user->id,
            'profile_id' => $profileId,
            'name' => 'Family iPad',
            'device_type' => 'tablet',
            'device_id' => 'dev-ipad-01',
            'public_ip' => '203.0.113.25',
        ]);

        $publishResponse = $this->postJson("/api/v1/member/profiles/{$profileId}/publish", [
            'profile' => ['default_action' => 'allow'],
            'rules' => [],
            'features' => [],
        ]);

        $publishResponse->assertOk()
            ->assertJsonPath('data.payload.config_json.default_action', 'block')
            ->assertJsonPath('data.payload.config_json.rules.0.domain', 'tracker.example.com')
            ->assertJsonPath('data.payload.config_json.devices.0.device_id', 'dev-ipad-01');

        $this->assertDatabaseCount('profile_versions', 1);

        /** @var ProfileVersion $version */
        $version = ProfileVersion::query()->firstOrFail();
        $this->assertSame('published', $version->status);
        $this->assertSame('block', $version->config_json['default_action']);
        $this->assertSame('tracker.example.com', $version->config_json['rules'][0]['domain']);
        $this->assertNotSame('allow', $version->config_json['default_action']);
        $this->assertNotNull($ruleResponse->json('data.id'));
    }

    public function test_member_logs_and_analytics_can_use_dns_console_internal_api(): void
    {
        // 2026-06-15 merge 后 query_log_entries 已经直接写在 portal-web 的 PostgreSQL，
        // 这里走 in-process DB 路径构造日志，不再 mock dns-console HTTP。
        $user = $this->createUser('workspace4@example.com');
        $profile = $user->profiles()->create([
            'name' => 'Home Profile',
            'description' => 'Primary profile',
            'default_action' => 'allow',
            'block_response' => 'nxdomain',
            'security_enabled' => true,
            'privacy_enabled' => true,
            'parental_enabled' => false,
            'safe_search_enabled' => false,
            'log_mode' => 'full',
        ]);
        Device::create([
            'user_id' => $user->id,
            'profile_id' => $profile->id,
            'name' => 'MacBook',
            'device_type' => 'desktop',
            'device_id' => 'dev-home-01',
        ]);

        $node = Node::create([
            'id' => 'node-test-01',
            'node_name' => 'test-node',
            'status' => 'active',
            'region' => 'ap-northeast-1',
            'approved_at' => now(),
        ]);
        $batch = QueryLogIngestBatch::create([
            'id' => 'qlb-test-01',
            'batch_id' => 'batch-test-01',
            'node_id' => $node->id,
            'item_count' => 2,
            'content_sha256' => str_repeat('a', 64),
            'status' => 'written',
            'received_at' => now(),
            'written_at' => now(),
        ]);

        $now = now();
        QueryLogEntry::create([
            'ingest_batch_id' => $batch->id,
            'node_id' => $node->id,
            'user_id' => $user->id,
            'profile_id' => $profile->id,
            'device_id' => 'dev-home-01',
            'query_name' => 'tracker.example.com',
            'query_type' => 'A',
            'action' => 'blocked',
            'reason' => 'denylist',
            'category' => 'custom',
            'rcode' => 0,
            'latency_ms' => 12,
            'queried_at' => $now,
            'created_at' => $now,
        ]);
        QueryLogEntry::create([
            'ingest_batch_id' => $batch->id,
            'node_id' => $node->id,
            'user_id' => $user->id,
            'profile_id' => $profile->id,
            'device_id' => 'dev-home-01',
            'query_name' => 'openai.com',
            'query_type' => 'A',
            'action' => 'allowed',
            'rcode' => 0,
            'latency_ms' => 8,
            'queried_at' => $now->copy()->subMinute(),
            'created_at' => $now->copy()->subMinute(),
        ]);

        Sanctum::actingAs($user, [], 'api');

        $this->getJson('/api/v1/member/logs')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.profile_name', 'Home Profile')
            ->assertJsonPath('data.0.device', 'MacBook');

        $this->getJson('/api/v1/member/analytics')
            ->assertOk()
            ->assertJsonPath('data.today_queries', 2)
            ->assertJsonPath('data.today_blocked', 1);
    }

    private function createUser(string $email, string $password = 'password123'): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => $email,
            'password' => Hash::make($password),
            'timezone' => 'UTC',
            'locale' => 'en',
            'role' => 'member',
            'status' => 'active',
            'plan_code' => 'free',
        ]);
    }
}
