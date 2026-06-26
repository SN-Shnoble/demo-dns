# 任务：DNS 解析器完整查询链路测试

## 目标

验证 dns-resolver 从 portal-web 拉取用户策略配置，并在 DNS 查询中正确识别、匹配和执行所有策略控制。

## 环境准备

### 1. 启动 portal-web（Laravel 后端）

```bash
cd /Users/472733389qq.com/Desktop/ai-agent/docs/ai-doc/ai-doc/ocer-dns/portal-web

# 确保数据库迁移完成
php artisan migrate

# 创建测试用 API 密钥（节点注册用）
php artisan tinker --execute="
    use App\Models\Node;
    use Illuminate\Support\Str;
    \$node = Node::create([
        'node_uid' => 'test-node-01',
        'node_alias' => 'Test Local Node',
        'api_key' => 'test_api_key_abc123',
        'status' => 'installed',
        'region' => 'local',
    ]);
    echo \$node->id;
"

# 启动开发服务器
php artisan serve --host=0.0.0.0 --port=8000
```

### 2. 通过 API 创建测试用户和策略

使用 curl 或 Laravel Tinker 创建以下测试数据：

#### a) 创建用户 "abc"（密码 123456）

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "abc@test.com",
    "username": "abc",
    "password": "123456",
    "password_confirmation": "123456"
  }'
```

#### b) 获取用户 token 并创建 Profile

```bash
# 登录获取 token
TOKEN=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"abc@test.com","password":"123456"}' | jq -r '.data.token')

# 创建 Profile
curl -X POST http://localhost:8000/api/user/profiles \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Test Profile"}'
```

#### c) 配置策略（通过 Portal API 或数据库直写）

需要配置的测试策略：

| 策略类型     | 项目      | 配置值                                         |
| :------- | :------ | :------------------------------------------ |
| **白名单**  | 允许放行    | `allow.example.com`（精确） `*.trusted.com`（通配） |
| **黑名单**  | 阻断      | `block.example.com`（精确） `*.bad.com`（通配）     |
| **安全防护** | 恶意软件    | 启用，添加 `malware.test.com`                    |
| **安全防护** | 钓鱼      | 启用，添加 `phishing.test.com`                   |
| **安全防护** | DGA 检测  | 启用，熵阈值 4.2                                  |
| **安全防护** | IDN 同形字 | 启用                                          |
| **安全防护** | 域名仿冒    | 启用，品牌域名 `["example.com"]`                   |
| **隐私防护** | 广告跟踪器   | 启用，添加 `tracker.example.com`                 |
| **隐私防护** | 伪装跟踪器   | 启用                                          |
| **家长控制** | 成人内容    | 启用，添加 `adult.test.com`                      |
| **家长控制** | 赌博      | 启用，添加 `gambling.test.com`                   |
| **家长控制** | 社交网络    | 启用，添加 `social.test.com`                     |
| **家长控制** | 安全搜索    | 启用                                          |
| **默认动作** | 未匹配时    | 放行 (ALLOW)                                  |

### 3. 发布配置

```bash
# 通过管理后台或直接创建 ConfigVersion
curl -X POST http://localhost:8000/api/admin/publish \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"profile_id": "<profile_id>", "version": 1}'
```

### 4. 配置并启动 dns-resolver

```bash
cd /Users/472733389qq.com/Desktop/ai-agent/docs/ai-doc/ai-doc/ocer-dns/dns-resolver

# 创建 server.yaml
cat > /tmp/dns-resolver.yaml << 'EOF'
node:
  node_uid: "test-node-01"
  name: "Test Local Node"
  version: "1.0.0"
  region: "local"
  country: "CN"
  provider: "local"
  public_ipv4: "127.0.0.1"
  supported_protocols: ["udp", "tcp", "doh"]

listen:
  udp: 5353
  tcp: 5353
  doh: 0

control_plane:
  endpoint: "http://localhost:8000"
  node_id: "test-node-01"
  heartbeat_interval: 30
  config_poll_interval: 30
  profiles_path: "./data/profiles"
  profiles_cache_dir: "./data/profiles"
  profile_cache_memory: 5000
  profile_cache_disk: 20000
  profile_evict_ttl_min: 30
  profile_disk_ttl_days: 7
  version_check_minutes: 1
  request_timeout_seconds: 5

api_key_path: "./test_api_key"

logging:
  level: "debug"
  buffer_path: "./data/logs"

cache:
  enabled: false
EOF

# 写入 API Key 文件
echo -n "test_api_key_abc123" > ./test_api_key

# 启动 resolver（调试模式）
go run ./cmd/dns-resolver --config /tmp/dns-resolver.yaml 2>&1 | tee resolver.log
```

## 测试用例

### 测试 1：心跳与全局配置拉取

**验证点**：

- 启动后 30 秒内观察到心跳日志
- Portal 端 `nodes` 表 `last_heartbeat_at` 更新
- `global.json` 文件生成在 profiles 目录下

**检查**：

```bash
# 查看 resolver 日志
grep -E "heartbeat|Global config" resolver.log

# 查看 Portal 端节点状态
mysql -e "SELECT id, node_alias, last_heartbeat_at, runtime_status FROM dns_resolver_nodes WHERE node_uid='test-node-01';"
```

### 测试 2：Profile 按需拉取

**验证点**：

- DNS 查询触发 Profile 拉取
- 经过 Memory Cache → Disk Cache → Portal 三级回源
- 日志打印 `FetchProfile` 和缓存状态

**方法**：

```bash
# 使用 dig 发起测试查询（通过 UDP :5353 的 DoH 路径）
dig @127.0.0.1 -p 5353 allow.example.com

# 检查日志
grep -E "FetchProfile|profile.*fetched|cache" resolver.log
```

### 测试 3：白名单优先匹配

**验证点**：白名单域名即使出现在黑名单中也应当 ALLOW（优先级 Level 1 > Level 2）

| 测试域名                | 期望结果  | 期望 Reason         |
| :------------------ | :---- | :---------------- |
| `allow.example.com` | ALLOW | `allowlist`       |
| `sub.trusted.com`   | ALLOW | `allowlist`（通配匹配） |
| `block.example.com` | BLOCK | `denylist`        |
| `sub.bad.com`       | BLOCK | `denylist`（通配匹配）  |

```bash
# 测试命令
dig @127.0.0.1 -p 5353 allow.example.com
dig @127.0.0.1 -p 5353 sub.trusted.com
dig @127.0.0.1 -p 5353 block.example.com
dig @127.0.0.1 -p 5353 sub.bad.com

# 验证日志
grep "action=" resolver.log
```

### 测试 4：安全防护匹配

**验证点**：安全类别域名被 BLOCK，Category 正确

| 测试域名                | 期望结果  | 期望 Reason  | 期望 Category |
| :------------------ | :---- | :--------- | :---------- |
| `malware.test.com`  | BLOCK | `security` | `malware`   |
| `phishing.test.com` | BLOCK | `security` | `phishing`  |
| `normal-site.com`   | ALLOW | `default`  | -           |

### 测试 5：安全算法引擎

**验证点**：DGA / IDN / Typosquatting 等算法检测

| 测试域名                           | 期望结果  | 说明                                 |
| :----------------------------- | :---- | :--------------------------------- |
| `xn--mgba3a4f16a.com`（IDN 同形字） | BLOCK | IDN Homograph 检测                   |
| `g00g1e.com`（高熵随机字符串）          | BLOCK | DGA 检测（高熵 > 4.2）                   |
| `examp1e.com`（形近字替换）           | BLOCK | Typosquatting（Levenshtein 距离 <= 1） |
| `example.gq`（高危 TLD）           | BLOCK | Blocked TLD                        |
| `test.ddns.net`（动态 DNS）        | BLOCK | Dynamic DNS 检测                     |

### 测试 6：隐私防护 / 广告拦截

**验证点**：广告跟踪器域名被 BLOCK

| 测试域名                  | 期望结果  | 期望 Reason             |
| :-------------------- | :---- | :-------------------- |
| `tracker.example.com` | BLOCK | `adblock` 或 `privacy` |
| `ads.doubleclick.net` | BLOCK | `adblock`             |

### 测试 7：家长控制

**验证点**：家长控制类别域名被 BLOCK，Category 正确

| 测试域名                | 期望结果  | 期望 Reason  | 期望 Category    |
| :------------------ | :---- | :--------- | :------------- |
| `adult.test.com`    | BLOCK | `parental` | `adult`        |
| `gambling.test.com` | BLOCK | `parental` | `gambling`     |
| `social.test.com`   | BLOCK | `parental` | `social_media` |

### 测试 8：安全搜索

**验证点**：安全搜索启用时，主流搜索引擎被 REWRITE

| 测试域名              | 期望结果                                   | 说明     |
| :---------------- | :------------------------------------- | :----- |
| `www.google.com`  | REWRITE → `forcesafesearch.google.com` | <br /> |
| `www.bing.com`    | REWRITE → `strict.bing.com`            | <br /> |
| `www.youtube.com` | REWRITE → `restrict.youtube.com`       | <br /> |

### 测试 9：默认放行

**验证点**：未匹配任何规则的域名默认 ALLOW

```bash
dig @127.0.0.1 -p 5353 completely-unknown-site.com
# 期望日志: action=ALLOW reason=default
```

### 测试 10：版本检查与配置更新

**验证点**：新发布配置后，resolver 能检测到并重新拉取

```bash
# 1. 修改并重新发布配置（增加一个黑名单域名）
# 2. 等待版本检查周期（默认 1 分钟）
grep "checkProfiles\|profiles.*updated" resolver.log

# 3. 查询新添加的黑名单域名
dig @127.0.0.1 -p 5353 newly-blocked-domain.com
# 期望: BLOCK（新规则已生效）
```

## 验证清单

- 心跳正常上报，Portal 端节点状态为 `online`
- 全局配置拉取成功（upstreams / plans / limits）
- Profile 按需拉取（首次查询触发回源）
- 内存缓存生效（第二次相同 profile 查询不触发回源）
- 白名单域名 ALLOW（Level 1 优先）
- 黑名单域名 BLOCK
- 安全防护类别 BLOCK（malware / phishing）
- DGA 算法检测生效
- IDN 同形字检测生效
- 域名仿冒检测生效
- 高危 TLD 阻断
- 动态 DNS 阻断
- 广告跟踪器 BLOCK
- 家长控制 BLOCK（adult / gambling / social\_media）
- 安全搜索 REWRITE 生效
- 未匹配域名默认 ALLOW
- 发布新配置后，版本检查机制触发重新拉取
- 规则变更在下一个查询生效

## 关键日志字段说明
