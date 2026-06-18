package main

import (
	"os"
	"testing"
)

// TestResolveConfigPath 覆盖三档优先级以及回退路径。
//
// 我们不直接断言 defaultConfigPath 的字面量（CI / 容器内的 CWD 可能不同），
// 改用"在三种来源中分别给一个不冲突的标记字符串"做端到端检查。
func TestResolveConfigPath(t *testing.T) {
	const (
		flagPath  = "/tmp/from-flag.yaml"
		envPath   = "/tmp/from-env.yaml"
		unsetPath = "/tmp/from-default.yaml"
	)
	const envKey = "RESOLVER_CONFIG"

	// 用 t.Setenv 替代裸 os.Setenv：测试结束自动还原，goroutine 安全。
	t.Run("flag beats env and default", func(t *testing.T) {
		t.Setenv(envKey, envPath)
		got, err := resolveConfigPath([]string{"--config=" + flagPath})
		if err != nil {
			t.Fatalf("unexpected error: %v", err)
		}
		if got != flagPath {
			t.Fatalf("expected flag path %q, got %q", flagPath, got)
		}
	})

	t.Run("env used when flag empty", func(t *testing.T) {
		t.Setenv(envKey, envPath)
		got, err := resolveConfigPath(nil)
		if err != nil {
			t.Fatalf("unexpected error: %v", err)
		}
		if got != envPath {
			t.Fatalf("expected env path %q, got %q", envPath, got)
		}
	})

	t.Run("env used when flag is empty string", func(t *testing.T) {
		t.Setenv(envKey, envPath)
		got, err := resolveConfigPath([]string{"--config="})
		if err != nil {
			t.Fatalf("unexpected error: %v", err)
		}
		if got != envPath {
			t.Fatalf("expected env path %q (flag was empty), got %q", envPath, got)
		}
	})

	t.Run("default used when env unset", func(t *testing.T) {
		// 直接 unsetenv，避免外部环境干扰
		if err := os.Unsetenv(envKey); err != nil {
			t.Fatalf("unsetenv failed: %v", err)
		}
		got, err := resolveConfigPath(nil)
		if err != nil {
			t.Fatalf("unexpected error: %v", err)
		}
		if got != defaultConfigPath {
			t.Fatalf("expected default %q, got %q", defaultConfigPath, got)
		}
		if got != unsetPath && got == "" {
			t.Fatalf("default path is empty: %q", got)
		}
	})

	t.Run("whitespace-only flag falls back to env", func(t *testing.T) {
		t.Setenv(envKey, envPath)
		got, err := resolveConfigPath([]string{"--config=   "})
		if err != nil {
			t.Fatalf("unexpected error: %v", err)
		}
		if got != envPath {
			t.Fatalf("expected env fallback %q for whitespace flag, got %q", envPath, got)
		}
	})

	t.Run("unknown flag is reported", func(t *testing.T) {
		if _, err := resolveConfigPath([]string{"--bogus"}); err == nil {
			t.Fatal("expected error for unknown flag, got nil")
		}
	})

	t.Run("separator form --config PATH works", func(t *testing.T) {
		t.Setenv(envKey, envPath)
		got, err := resolveConfigPath([]string{"--config", flagPath})
		if err != nil {
			t.Fatalf("unexpected error: %v", err)
		}
		if got != flagPath {
			t.Fatalf("expected flag path %q via separator form, got %q", flagPath, got)
		}
	})
}
