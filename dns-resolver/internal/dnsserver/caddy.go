package dnsserver

import (
	"crypto/x509"
	"encoding/pem"
	"fmt"
	"log"
	"os"
	"path/filepath"
	"strings"
	"time"
)

// findCaddyCert 在 Caddy 证书存储中查找指定域名最合适的证书。
// Caddy 标准存储路径（Debian/Ubuntu apt）：
//
//	/var/lib/caddy/.local/share/caddy/certificates/<acme-endpoint>/<domain>/
//
// 当多个 ACME 端点（如 production + staging）同时存在时，
// 解析 x509 证书，选择 NotAfter 最大且未过期的证书。
// 返回 (certPath, keyPath, error)，不复制，直接引用 Caddy 原始文件。
func findCaddyCert(domain string) (certFile, keyFile string, err error) {
	if domain == "" {
		return "", "", fmt.Errorf("domain is empty")
	}

	pattern := fmt.Sprintf(
		"/var/lib/caddy/.local/share/caddy/certificates/*/%s/%s.crt",
		domain, domain,
	)
	matches, _ := filepath.Glob(pattern)
	log.Printf("caddy cert: searching %q → %d matches", pattern, len(matches))

	if len(matches) == 0 {
		// 诊断：检查 Caddy 证书目录下有哪些域名
		if dirs, dirErr := filepath.Glob("/var/lib/caddy/.local/share/caddy/certificates/*/*/"); dirErr == nil && len(dirs) > 0 {
			log.Printf("caddy cert: available domains: %v", dirs)
		} else {
			log.Printf("caddy cert: no certificates found at all in /var/lib/caddy/.local/share/caddy/certificates/ (Caddy may not have obtained them yet)")
		}
		return "", "", fmt.Errorf("Caddy certificate not found for domain %q", domain)
	}

	// 多 ACME 端点时选择最佳证书
	if len(matches) > 1 {
		log.Printf("caddy cert: multiple ACME endpoints found, selecting best certificate")
		var bestCert string
		var bestNotAfter time.Time
		for _, m := range matches {
			data, readErr := os.ReadFile(m)
			if readErr != nil {
				log.Printf("caddy cert: skipping %s (unreadable: %v)", m, readErr)
				continue
			}
			block, _ := pem.Decode(data)
			if block == nil {
				log.Printf("caddy cert: skipping %s (not valid PEM)", m)
				continue
			}
			cert, parseErr := x509.ParseCertificate(block.Bytes)
			if parseErr != nil {
				log.Printf("caddy cert: skipping %s (parse error: %v)", m, parseErr)
				continue
			}
			if cert.NotAfter.After(time.Now()) && cert.NotAfter.After(bestNotAfter) {
				bestCert = m
				bestNotAfter = cert.NotAfter
				log.Printf("caddy cert: candidate %s (expires %s)", m, cert.NotAfter.Format(time.RFC3339))
			}
		}
		if bestCert == "" {
			return "", "", fmt.Errorf("no valid (non-expired) certificate found for domain %q", domain)
		}
		certFile = bestCert
		log.Printf("caddy cert: selected %s (expires %s)", certFile, bestNotAfter.Format(time.RFC3339))
	} else {
		certFile = matches[0]
	}

	keyFile = strings.Replace(certFile, ".crt", ".key", 1)

	// 权限检查：确认 resolver 进程能读取证书文件
	if _, statErr := os.Stat(keyFile); statErr != nil {
		return "", "", fmt.Errorf("Caddy key file not accessible: %s — check file permissions", keyFile)
	}
	certData, readErr := os.ReadFile(certFile)
	if readErr != nil {
		return "", "", fmt.Errorf("Caddy cert file not readable: %s — check file permissions: %w", certFile, readErr)
	}
	if len(certData) == 0 {
		return "", "", fmt.Errorf("Caddy cert file is empty: %s", certFile)
	}

	log.Printf("caddy cert: referencing cert=%s key=%s — no copy", certFile, keyFile)
	return certFile, keyFile, nil
}
