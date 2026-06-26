package rules

import (
	"math"
	"strings"
	"unicode"
)

// DGAResult 标识一个域名是否被判定为 DGA（算法生成域名）
type DGAResult struct {
	Blocked bool
	Reason  string
	Entropy float64
	Ratio   float64
	Length  int
}

// CheckDGA 使用 Shannon 熵 + 数字占比 + 长度启发式判断 DGA 域名。
// DGA 特征：随机字符组合 → 高熵（>4.0），大量数字（>40%），长度异常（>=12）。
//
// entropyThreshold 建议 4.0~5.0，digitRatio 建议 0.4~0.8。
// 三个特征同时满足时返回 Block。
func CheckDGA(domain string, entropyThreshold, digitRatio float64) DGAResult {
	dn := strings.TrimSuffix(strings.ToLower(domain), ".")
	if dn == "" {
		return DGAResult{}
	}

	// 取主标签部分（去除 TLD）
	parts := strings.Split(dn, ".")
	sld := parts[0]
	if sld == "" {
		return DGAResult{}
	}

	length := len(sld)
	entropy := shannonEntropy(sld)
	digits := 0
	letters := 0
	for _, r := range sld {
		if unicode.IsDigit(r) {
			digits++
		} else if unicode.IsLetter(r) {
			letters++
		}
	}

	ratio := 0.0
	if letters+digits > 0 {
		ratio = float64(digits) / float64(letters+digits)
	}

	res := DGAResult{
		Entropy: entropy,
		Ratio:   ratio,
		Length:  length,
	}

	// 启发式判定：以下条件满足任一即可标记为 DGA
	// 1) 长 + 高熵 + 高数字占比（典型 DGA）
	// 2) 超长 + 极高熵（即便无数字）
	// 3) 极长 + 高数字占比
	highEntropy := entropy >= entropyThreshold
	highDigit := ratio >= digitRatio
	long := length >= 12
	veryLong := length >= 18
	extremeEntropy := entropy >= 4.7

	if (long && highEntropy && highDigit) || (veryLong && extremeEntropy) || (veryLong && highDigit) {
		res.Blocked = true
		res.Reason = "dga-heuristic"
		return res
	}

	return res
}

// shannonEntropy 计算字符串的 Shannon 熵（log2 底）
func shannonEntropy(s string) float64 {
	if s == "" {
		return 0
	}
	freq := make(map[rune]int)
	for _, r := range s {
		freq[r]++
	}
	n := float64(len(s))
	var h float64
	for _, c := range freq {
		p := float64(c) / n
		h -= p * math.Log2(p)
	}
	return h
}
