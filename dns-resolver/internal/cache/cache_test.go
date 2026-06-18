package cache

import (
	"context"
	"testing"
	"time"

	"ocer-dns/dns-resolver/internal/config"
)

func TestNew_disabled_returns_noop(t *testing.T) {
	cache := New(config.RedisConfig{Enabled: false, Addr: "127.0.0.1:6379"})
	if cache.Enabled() {
		t.Fatalf("expected disabled cache when Enabled=false")
	}
	if err := cache.Close(); err != nil {
		t.Fatalf("disabled cache Close should not error: %v", err)
	}
	first, err := cache.MarkSeen(context.Background(), "k1", time.Second)
	if err != nil {
		t.Fatalf("disabled MarkSeen should not error: %v", err)
	}
	if !first {
		t.Fatalf("disabled MarkSeen should report firstSeen=true")
	}
}

func TestNew_enabled_but_unreachable_stays_disabled(t *testing.T) {
	// 127.0.0.1:1 is the canonical "nothing listens here" address.
	cache := New(config.RedisConfig{Enabled: true, Addr: "127.0.0.1:1"})
	if cache.Enabled() {
		t.Fatalf("expected disabled cache when ping fails")
	}
}

func TestNew_enabled_with_empty_addr_stays_disabled(t *testing.T) {
	cache := New(config.RedisConfig{Enabled: true, Addr: ""})
	if cache.Enabled() {
		t.Fatalf("expected disabled cache when addr is empty")
	}
}

func TestMarkSeen_empty_key_errors(t *testing.T) {
	cache := newEnabledCache(100 * time.Millisecond)
	_, err := cache.MarkSeen(context.Background(), "", time.Second)
	if err == nil {
		t.Fatalf("expected error on empty dedup key")
	}
}

func TestIncr_empty_key_errors(t *testing.T) {
	cache := newEnabledCache(100 * time.Millisecond)
	_, err := cache.Incr(context.Background(), "", time.Second)
	if err == nil {
		t.Fatalf("expected error on empty counter key")
	}
}

// newEnabledCache returns a Cache whose enabled flag is set, without going
// through New() — that way we can unit-test the input-validation paths
// without needing a real Redis.
func newEnabledCache(timeout time.Duration) *Cache {
	c := &Cache{timeout: timeout}
	c.enabled.Store(true)
	return c
}
