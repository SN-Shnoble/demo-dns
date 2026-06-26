package rules

import (
	"strings"
)

// TLDResult 标识一个域名因 TLD 被拦截的结果
type TLDResult struct {
	Blocked bool
	Reason  string
	TLD     string
}

// dangerousTLDs 是通常与恶意活动相关联的顶级域名。
// 这些 TLD 大多为免费注册、审查宽松，常被用于钓鱼/恶意软件分发。
var dangerousTLDs = map[string]bool{
	// Freenom 免费 TLDs（2023 年已停止注册，但存量仍被广泛滥用）
	"tk": true,
	"ml": true,
	"ga": true,
	"cf": true,
	"gq": true,
	// 常见的被滥用 TLDs
	"top":        true,
	"loan":       true,
	"win":        true,
	"bid":        true,
	"date":       true,
	"trade":      true,
	"men":        true,
	"download":   true,
	"review":     true,
	"click":      true,
	"work":       true,
	"party":      true,
	"science":    true,
	"racing":     true,
	"accountant": true,
}

// CheckBlockedTLD 检查域名的顶级域名是否在危险 TLD 列表中。
func CheckBlockedTLD(domain string) TLDResult {
	dn := strings.TrimSuffix(strings.ToLower(domain), ".")
	if dn == "" {
		return TLDResult{}
	}

	parts := strings.Split(dn, ".")
	if len(parts) < 2 {
		return TLDResult{}
	}

	tld := parts[len(parts)-1]
	if dangerousTLDs[tld] {
		return TLDResult{
			Blocked: true,
			Reason:  "blocked-tld",
			TLD:     tld,
		}
	}

	return TLDResult{TLD: tld}
}

// IsDangerousTLD 对外暴露的 TLD 查询函数。
func IsDangerousTLD(tld string) bool {
	return dangerousTLDs[strings.ToLower(tld)]
}
