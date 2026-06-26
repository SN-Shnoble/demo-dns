package rules

import (
	"strings"
)

// TyposquattingResult 标识一个域名是否被判定为误植攻击
type TyposquattingResult struct {
	Blocked   bool
	Reason    string
	Brand     string
	Distance  int
}

// CheckTyposquatting 检查域名是否与品牌列表中某个品牌的注册域名
// 仅有 ≤threshold 个字符差异（Levenshtein 距离）。
//
// threshold 1 = 仅拦截精确 typo（如 "go0gle.com"）
// threshold 2 = 同时拦截插入字符、删除字符、替换字符的近似变体
//
// 注册品牌不区分大小写匹配；查询域名去除光标字符后比较。
func CheckTyposquatting(domain string, brandDomains []string, threshold int) TyposquattingResult {
	dn := cleanDomain(domain)
	if dn == "" {
		return TyposquattingResult{}
	}
	if threshold < 1 {
		threshold = 1
	}
	if threshold > 2 {
		threshold = 2
	}

	for _, brand := range brandDomains {
		b := cleanDomain(brand)
		if b == "" {
			continue
		}
		// 完全匹配不算 typo
		if dn == b {
			continue
		}
		// 必须都是多级域名的根域 (SLD.TLD) 才比较，避免 *.google.com 子域误报
		if !isApex(b) {
			continue
		}
		// 顶级域不同直接跳过
		if tldOf(dn) != tldOf(b) {
			continue
		}
		dist := levenshtein(sldOf(dn), sldOf(b))
		if dist > 0 && dist <= threshold {
			return TyposquattingResult{
				Blocked:  true,
				Reason:   "typosquatting",
				Brand:    b,
				Distance: dist,
			}
		}
	}
	return TyposquattingResult{}
}

func cleanDomain(d string) string {
	d = strings.TrimSpace(d)
	d = strings.TrimSuffix(strings.ToLower(d), ".")
	if idx := strings.Index(d, "*."); idx >= 0 {
		d = d[idx+2:]
	}
	return d
}

func isApex(d string) bool {
	return strings.Count(d, ".") == 1
}

func tldOf(d string) string {
	idx := strings.LastIndex(d, ".")
	if idx < 0 {
		return ""
	}
	return d[idx+1:]
}

func sldOf(d string) string {
	idx := strings.LastIndex(d, ".")
	if idx < 0 {
		return d
	}
	return d[:idx]
}

// levenshtein 经典 DP 实现，O(n*m) 时间 / O(min(n,m)) 空间
func levenshtein(a, b string) int {
	if a == b {
		return 0
	}
	la, lb := len(a), len(b)
	if la == 0 {
		return lb
	}
	if lb == 0 {
		return la
	}
	if la < lb {
		a, b = b, a
		la, lb = lb, la
	}
	prev := make([]int, lb+1)
	curr := make([]int, lb+1)
	for j := 0; j <= lb; j++ {
		prev[j] = j
	}
	for i := 1; i <= la; i++ {
		curr[0] = i
		for j := 1; j <= lb; j++ {
			cost := 1
			if a[i-1] == b[j-1] {
				cost = 0
			}
			del := prev[j] + 1
			ins := curr[j-1] + 1
			sub := prev[j-1] + cost
			min := del
			if ins < min {
				min = ins
			}
			if sub < min {
				min = sub
			}
			curr[j] = min
		}
		prev, curr = curr, prev
	}
	return prev[lb]
}
