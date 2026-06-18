# dns-resolver

Go single-binary DNS service for:

- UDP and DoH query handling
- profile identification
- local rule execution
- config pull and hot reload
- heartbeat, metrics, and query log batch reporting

## 唯一安装方式：Console 预签发凭据

dns-resolver 节点的生命周期严格遵循：

```text
Console 后台
   ├─ 创建节点（NodeID = hk-01）
   └─ 签发凭据 (api_key=ak_xxx, secret=sk_xxx)
        ↓
   运维拷贝到目标机
        ↓
$ resolver install \
    --console=https://console.ocerlink.com \
    --node-id=hk-01 \
    --api-key=ak_xxx \
    --secret=sk_xxx
        ↓
   写 configs/server.yaml（原子写，0600）
        ↓
$ resolver                # 直接启动
        ↓
   agent 读取控制面凭据
   api_key → Authorization: Bearer ...
   secret  → X-Hmac-Key + X-Signature (HMAC-SHA256)
   启动 cfg.Validate() 拒绝任何缺凭据场景
```

**不存在**以下分支：

- ❌ `bootstrap_token` + `/api/v1/agent/nodes/register` 自助注册
- ❌ `identity.json` 落盘后再启
- ❌ "如果 api_key 缺失就走旧流程" 的兜底 / 回退
- ❌ 占位 stub、虚拟客户端、空方法

任何凭据字段（`api_key` / `secret` / `node_id`）缺失，resolver 启动时 `cfg.Validate()` 直接 `log.Fatalf`，**绝不进入降级路径**。

## 子命令

```text
resolver                启动 dns-resolver 守护进程（加载 configs/server.yaml）
resolver run            同上
resolver install ...    把 console 预签发凭据写入 configs/server.yaml
resolver help           打印帮助
```

## Implement first

- config loader with checksum validation
- heartbeat loop
- config pull and ack
- rule engine with allow-first precedence
- DoH path and UDP source IP profile resolution
- UDP and DoH handlers
- local batch buffer and replay
