package rules

import (
	"net"
	"testing"
)

func TestCheckDNSRebinding(t *testing.T) {
	tests := []struct {
		name     string
		domain   string
		ip       string
		expected bool
	}{
		{"public-ip", "example.com", "8.8.8.8", false},
		{"rfc1918", "evil.com", "192.168.1.1", true},
		{"loopback", "evil.com", "127.0.0.1", true},
		{"linklocal", "evil.com", "169.254.169.254", true},
		{"rfc4193", "evil.com", "fd00::1", true},
		{"unspecified", "evil.com", "0.0.0.0", true},
	}
	whitelist := []string{"localhost", "*.local"}
	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			ip := net.ParseIP(tc.ip)
			got := CheckDNSRebinding(tc.domain, ip, whitelist).Blocked
			if got != tc.expected {
				t.Errorf("domain=%s ip=%s: got %v, want %v", tc.domain, tc.ip, got, tc.expected)
			}
		})
	}

	// 验证 whitelist 命中放行
	res := CheckDNSRebinding("router.local", net.ParseIP("192.168.1.1"), []string{"*.local"})
	if res.Blocked {
		t.Error("expected *.local whitelist to allow 192.168.1.1")
	}
}

func TestCheckIDNHomograph(t *testing.T) {
	// 纯 ASCII 域名应放行
	r := CheckIDNHomograph("google.com", true)
	if r.Blocked {
		t.Error("expected google.com to be allowed")
	}

	// Cyrillic а 替换 Latin a（non-ASCII + ascii-only 模式）
	r = CheckIDNHomograph("аpple.com", true)
	if !r.Blocked {
		t.Error("expected IDN attack domain to be blocked in ascii-only mode")
	}
}

func TestCheckTyposquatting(t *testing.T) {
	brands := []string{"google.com", "apple.com", "paypal.com"}

	tests := []struct {
		domain   string
		blocked  bool
	}{
		{"google.com", false},       // 完全匹配不算
		{"go0gle.com", true},        // 数字替换
		{"goggle.com", true},        // 字符替换
		{"aple.com", true},          // 删除字符
		{"paypaI.com", true},        // 大写 i 替换小写 L
		{"example.com", false},      // 不在品牌列表
		{"notgoogle.com", false},    // 子串差异超过阈值
		{"a.com", false},            // 太短
	}
	for _, tc := range tests {
		got := CheckTyposquatting(tc.domain, brands, 1).Blocked
		if got != tc.blocked {
			t.Errorf("domain=%s: got blocked=%v, want %v", tc.domain, got, tc.blocked)
		}
	}
}

func TestCheckDGA(t *testing.T) {
	// 正常域名
	r := CheckDGA("google.com", 4.0, 0.4)
	if r.Blocked {
		t.Errorf("expected google.com to be allowed, got %+v", r)
	}

	// DGA 域名: 长 + 高熵 + 高数字 (使用真实高熵模式)
	r = CheckDGA("x83hd92ks47flq29kd83hd92.com", 4.0, 0.4)
	if !r.Blocked {
		t.Errorf("expected DGA domain to be blocked, got %+v", r)
	}

	// 极高熵 + 极长自动判 DGA
	r = CheckDGA("abcdefghijklmnopqrstuvwxyz.com", 4.5, 0.5)
	if !r.Blocked {
		t.Errorf("expected ultra-long alphabet domain to be blocked, got %+v", r)
	}
}

func TestCheckBlockedTLD(t *testing.T) {
	tests := []struct {
		domain  string
		blocked bool
		tld     string
	}{
		{"example.tk", true, "tk"},
		{"evil.ml", true, "ml"},
		{"clickbank.top", true, "top"},
		{"www.google.com", false, "com"},
		{"github.io", false, "io"},
		{"example.org", false, "org"},
	}
	for _, tc := range tests {
		got := CheckBlockedTLD(tc.domain)
		if got.Blocked != tc.blocked {
			t.Errorf("domain=%s: got blocked=%v, want %v", tc.domain, got.Blocked, tc.blocked)
		}
		if got.TLD != tc.tld {
			t.Errorf("domain=%s: got tld=%s, want %s", tc.domain, got.TLD, tc.tld)
		}
	}
}

func TestCheckDynDNS(t *testing.T) {
	tests := []struct {
		domain   string
		blocked  bool
		provider string
	}{
		{"myhome.duckdns.org", true, "duckdns.org"},
		{"evil.no-ip.com", true, "no-ip.com"},
		{"router.asuscomm.com", true, "asuscomm.com"},
		{"example.com", false, ""},
		{"google.com", false, ""},
	}
	for _, tc := range tests {
		got := CheckDynDNS(tc.domain)
		if got.Blocked != tc.blocked {
			t.Errorf("domain=%s: got blocked=%v, want %v", tc.domain, got.Blocked, tc.blocked)
		}
	}
}

func TestCheckCNAMETracker(t *testing.T) {
	tests := []struct {
		cname    string
		blocked  bool
		provider string
	}{
		{"analytics.google-analytics.com", true, "google-analytics.com"},
		{"track.doubleclick.net", true, "doubleclick.net"},
		{"cdn.example.com", false, ""},
		{"api.github.com", false, ""},
	}
	for _, tc := range tests {
		got := CheckCNAMETracker(tc.cname)
		if got.Blocked != tc.blocked {
			t.Errorf("cname=%s: got blocked=%v, want %v", tc.cname, got.Blocked, tc.blocked)
		}
	}
}
