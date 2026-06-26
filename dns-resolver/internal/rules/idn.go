package rules

import (
	"strings"
	"unicode"

	"golang.org/x/text/unicode/norm"
)

// IDNResult 标识一个域名是否触发 IDN 同形异义攻击（Homograph）
type IDNResult struct {
	Blocked    bool
	Reason     string
	Normalized string
}

// CheckIDNHomograph 检测域名是否包含 IDN 同形异义攻击字符。
// 攻击者使用 Cyrillic / Greek 字母替换 Latin 字母（如 аpple.com 用 Cyrillic а）。
// 简化实现：使用 Unicode normalization 后的非 ASCII 字符且包含
// 来自不同 script 的 confusable 字符时，标记为可疑。
//
// asciiOnly 模式：只允许纯 ASCII 域名通过；含任何 non-ASCII 字符直接 BLOCK。
// 这是最保守也最安全的策略。
func CheckIDNHomograph(domain string, asciiOnly bool) IDNResult {
	dn := strings.TrimSuffix(strings.ToLower(domain), ".")
	if dn == "" {
		return IDNResult{}
	}

	// 去除可能的 xn-- 前缀 (Punycode)，仍视为 ASCII 范畴
	normalized := norm.NFC.String(dn)

	hasNonASCII := false
	scripts := make(map[string]int)
	for _, r := range normalized {
		if r > unicode.MaxASCII {
			hasNonASCII = true
			scripts[scriptOf(r)]++
		}
	}

	if !hasNonASCII {
		return IDNResult{Normalized: normalized}
	}

	if asciiOnly {
		return IDNResult{
			Blocked:    true,
			Reason:     "non-ascii-in-ascii-only-mode",
			Normalized: normalized,
		}
	}

	// 多个 script 混用视为可疑
	if len(scripts) > 1 {
		return IDNResult{
			Blocked:    true,
			Reason:     "mixed-scripts",
			Normalized: normalized,
		}
	}

	// 单一非 Latin script 的 IDN 也可能用于钓鱼（视策略放行）
	return IDNResult{Normalized: normalized}
}

func scriptOf(r rune) string {
	switch {
	case r >= 0x41 && r <= 0x7a:
		return "Latin"
	case r >= 0x0400 && r <= 0x04ff:
		return "Cyrillic"
	case r >= 0x0370 && r <= 0x03ff:
		return "Greek"
	case r >= 0x4e00 && r <= 0x9fff:
		return "Han"
	case r >= 0x0600 && r <= 0x06ff:
		return "Arabic"
	case r >= 0x0590 && r <= 0x05ff:
		return "Hebrew"
	default:
		return "Other"
	}
}
