#!/bin/bash
set -e
TS=$(date +%s)
NONCE=$(openssl rand -hex 16)
BODY='{"batch_id":"batch_test_001","node_id":"1","sent_at":"2026-06-20T03:00:00Z","items":[{"profile_id":"b669c1","device_id":"dev-localhost","query_name":"baidu.com","query_type":"A","action":"ALLOW","reason":"default","category":"","client_ip":"127.0.0.1","rcode":0,"latency_ms":5,"queried_at":1781926000}]}'
BODY_HASH=$(printf '%s' "$BODY" | shasum -a 256 | awk '{print $1}')
PATH_NORM="/api/v1/node/query-logs/batch"
SECRET='hmk_mfbkds0n7pb4jbunr7k7jroz2izojvnd'
CANONICAL=$(printf '%s\n%s\n%s\n%s' "$TS" "POST" "$PATH_NORM" "$BODY_HASH")
SIG=$(printf '%s' "$CANONICAL" | openssl dgst -sha256 -hmac "$SECRET" | awk '{print $2}')
echo "TS=$TS NONCE=$NONCE SIG=$SIG"
echo "--- response ---"
curl -i -X POST "http://localhost:8081${PATH_NORM}" \
  -H "Authorization: Bearer ocnd_aNLDX7QjKhKI2pVbPc0rFlroHGzD60lD9CtYglcx" \
  -H "X-Signature: $SIG" \
  -H "X-Timestamp: $TS" \
  -H "X-Nonce: $NONCE" \
  -H "X-Hmac-Key: $SECRET" \
  -H "Content-Type: application/json" \
  -d "$BODY" 2>&1
echo ""
echo "--- end ---"
