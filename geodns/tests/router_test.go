package tests

import (
	"testing"

	"ocer-dns/geodns/internal/healthview"
	"ocer-dns/geodns/internal/router"
)

func TestPickPrefersRegionalOnlineNode(t *testing.T) {
	r := router.New()
	nodes := []healthview.Node{
		{NodeID: "global-1", Region: "global", Status: "online", Weight: 10},
		{NodeID: "cn-1", Region: "cn", Status: "online", Weight: 20},
	}

	got := r.Pick("cn", nodes)
	if got == nil || got.NodeID != "cn-1" {
		t.Fatalf("expected cn-1, got %#v", got)
	}
}

func TestPickSkipsOfflineNode(t *testing.T) {
	r := router.New()
	nodes := []healthview.Node{
		{NodeID: "cn-1", Region: "cn", Status: "offline", Weight: 100},
		{NodeID: "cn-2", Region: "cn", Status: "online", Weight: 80},
	}

	got := r.Pick("cn", nodes)
	if got == nil || got.NodeID != "cn-2" {
		t.Fatalf("expected cn-2, got %#v", got)
	}
}

func TestPickPrefersHigherWeightWithinRegion(t *testing.T) {
	r := router.New()
	nodes := []healthview.Node{
		{NodeID: "cn-1", Region: "cn", Status: "online", Weight: 100},
		{NodeID: "cn-2", Region: "cn", Status: "online", Weight: 50},
	}

	got := r.Pick("cn", nodes)
	if got == nil || got.NodeID != "cn-1" {
		t.Fatalf("expected cn-1, got %#v", got)
	}
}
