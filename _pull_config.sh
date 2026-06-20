#!/bin/bash
# 模拟节点 1 主动拉取最新 config
set -e
TS=$(date +%s)
NONCE=$(openssl rand -hex 16)
PATH_NORM="/api/v1/node/resolver/config"
SECRET='hmk_mfbkds0n7pb4jbunr7k7jroz2izojvnd'
# GET 请求 body 为空
BODY_HASH=$(printf '' | shasum -a 256 | awk '{print $1}')
CANONICAL=$(printf '%s\n%s\n%s\n%s' "$TS" "GET" "$PATH_NORM" "$BODY_HASH")
SIG=$(printf '%s' "$CANONICAL" | openssl dgst -sha256 -hmac "$SECRET" | awk '{print $2}')

echo "TS=$TS NONCE=$NONCE SIG=$SIG"
echo "--- response (truncated) ---"
curl -s -X GET "http://localhost:8081${PATH_NORM}?node_id=1&current_version=0" \
  -H "Authorization: Bearer ocnd_aNLDX7QjKhKI2pVbPc0rFlroHGzD60lD9CtYglcx" \
  -H "X-Signature: $SIG" \
  -H "X-Timestamp: $TS" \
  -H "X-Nonce: $NONCE" \
  -H "X-Hmac-Key: $SECRET" 2>&1 | head -c 2000
echo ""
echo "--- end ---"
