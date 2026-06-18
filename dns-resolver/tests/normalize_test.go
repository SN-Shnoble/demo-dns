package tests

import (
	"testing"

	"ocer-dns/dns-resolver/internal/rules"
)

func TestNormalizeDomain(t *testing.T) {
	got, err := rules.NormalizeDomain("WWW.Example.COM.")
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	if got != "www.example.com" {
		t.Fatalf("expected normalized domain, got %s", got)
	}
}
