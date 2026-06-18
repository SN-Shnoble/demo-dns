package tests

import (
	"testing"

	"ocer-dns/dns-resolver/internal/profile"
)

func TestResolveDoHPath(t *testing.T) {
	resolver := profile.New(nil)

	got, err := resolver.ResolveDoHPath("/dns-query/prf_demo_01")
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	if got != "prf_demo_01" {
		t.Fatalf("expected profile id, got %s", got)
	}
}

func TestResolveSourceIP(t *testing.T) {
	resolver := profile.New(map[string]string{
		"127.0.0.1": "prf_demo_01",
	})

	got, err := resolver.ResolveSourceIP("127.0.0.1:5353")
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	if got != "prf_demo_01" {
		t.Fatalf("expected prf_demo_01, got %s", got)
	}
}
