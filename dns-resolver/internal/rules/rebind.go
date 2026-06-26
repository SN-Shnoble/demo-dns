// Package rules 提供 OcerDNS Resolver 端的安全检测算法。
// 这些算法与 Portal 端配置解耦——配置由 Portal 推送，Resolver 仅消费。
package rules

import (
	"net"
	"strings"
)

// DNSRebindingResult 标识一个 DNS Response 是否触发 Rebinding 攻击
type DNSRebindingResult struct {
	Blocked bool
	Reason  string
	IP      net.IP
}

// CheckDNSRebinding 检测 DNS 响应中的 IP 是否落入私有地址空间。
// 这是 DNS Rebinding 攻击的常见手法：攻击者域名首次解析返回公网 IP，
// 二次解析返回 192.168.x.x 绕过同源策略。
//
// 返回 BLOCK 当且仅当 IP 属于以下任一 RFC 保留段：
//   - RFC1918 私有地址  10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16
//   - RFC4193 唯一本地  fc00::/7
//   - Loopback          127.0.0.0/8, ::1
//   - LinkLocal         169.254.0.0/16, fe80::/10
//   - 0.0.0.0 / 未指定
//
// whitelist 列表（精确匹配）命中时始终放行，例如 "localhost", "*.local"。
func CheckDNSRebinding(domain string, ip net.IP, whitelist []string) DNSRebindingResult {
	if ip == nil {
		return DNSRebindingResult{}
	}

	dn := strings.ToLower(strings.TrimSuffix(domain, "."))
	for _, w := range whitelist {
		w = strings.ToLower(strings.TrimSpace(w))
		if w == "" {
			continue
		}
		// 支持 *.local 通配符
		if strings.HasPrefix(w, "*.") {
			suffix := strings.TrimPrefix(w, "*")
			if strings.HasSuffix(dn, suffix) {
				return DNSRebindingResult{IP: ip}
			}
			continue
		}
		if dn == w {
			return DNSRebindingResult{IP: ip}
		}
	}

	if ip.IsLoopback() || ip.IsLinkLocalUnicast() || ip.IsLinkLocalMulticast() || ip.IsPrivate() || ip.IsUnspecified() {
		return DNSRebindingResult{
			Blocked: true,
			Reason:  classifyReason(ip),
			IP:      ip,
		}
	}
	return DNSRebindingResult{IP: ip}
}

func classifyReason(ip net.IP) string {
	if ip.IsLoopback() {
		return "loopback"
	}
	if ip.IsLinkLocalUnicast() {
		return "linklocal-unicast"
	}
	if ip.IsLinkLocalMulticast() {
		return "linklocal-multicast"
	}
	if ip.IsPrivate() {
		if ip.To4() != nil {
			return "rfc1918"
		}
		return "rfc4193"
	}
	if ip.IsUnspecified() {
		return "unspecified"
	}
	return "private"
}
