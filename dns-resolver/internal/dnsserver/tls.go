package dnsserver

import (
	"crypto/ecdsa"
	"crypto/elliptic"
	"crypto/rand"
	"crypto/tls"
	"crypto/x509"
	"crypto/x509/pkix"
	"encoding/pem"
	"log"
	"math/big"
	"net"
	"os"
	"sync/atomic"
	"time"
)

// LoadTLSConfig 加载 TLS 配置。
// 参数策略（优先级递减）：
//  1. certFile/keyFile 非空 → 使用 GetCertificate 从固定路径热加载
//  2. 全部为空 → 生成自签名证书（开发测试用）
//
// GetCertificate 回调内部维护原子缓存：
//   - 首次启动无证书时 → 自签名兜底
//   - 一旦成功加载过正式证书 → 后续加载失败使用缓存证书，不回退自签名
func LoadTLSConfig(certFile, keyFile, dnsDomain string) (*tls.Config, error) {
	// 优先级 1：固定路径（install 期 certbot 写入的 fullchain.pem/privkey.pem）
	if certFile != "" && keyFile != "" {
		log.Printf("tls: will load certificate from %s (hot-reload via GetCertificate)", certFile)

		var (
			cachedCert  atomic.Value // stores *tls.Certificate
			hasRealCert atomic.Bool
		)

		if initialCert, err := tls.LoadX509KeyPair(certFile, keyFile); err == nil {
			cachedCert.Store(&initialCert)
			hasRealCert.Store(true)
			log.Printf("tls: preloaded certificate from %s", certFile)
		}

		return &tls.Config{
			GetCertificate: func(hello *tls.ClientHelloInfo) (*tls.Certificate, error) {
				cert, err := tls.LoadX509KeyPair(certFile, keyFile)
				if err != nil {
					if cached := cachedCert.Load(); cached != nil {
						log.Printf("tls: reload failed (%v) — using cached cert", err)
						return cached.(*tls.Certificate), nil
					}
					log.Printf("tls: no cached cert available, falling back to self-signed: %v", err)
					return generateSelfSignedCert()
				}
				cachedCert.Store(&cert)
				if !hasRealCert.Load() {
					hasRealCert.Store(true)
					log.Printf("tls: first successful load of %s", certFile)
				}
				return &cert, nil
			},
			MinVersion: tls.VersionTLS12,
		}, nil
	}

	// 优先级 2：无证书配置 → 自签名（开发测试）
	log.Printf("tls: no certificate configured, generating self-signed (dev-only)")
	c, err := generateSelfSignedCert()
	if err != nil {
		return nil, err
	}
	return &tls.Config{
		Certificates: []tls.Certificate{*c},
		MinVersion:   tls.VersionTLS12,
	}, nil
}

// generateSelfSignedCert 生成自签名 ECDSA 证书，有效期 365 天。
func generateSelfSignedCert() (*tls.Certificate, error) {
	key, err := ecdsa.GenerateKey(elliptic.P256(), rand.Reader)
	if err != nil {
		return nil, err
	}

	serial, err := rand.Int(rand.Reader, new(big.Int).Lsh(big.NewInt(1), 128))
	if err != nil {
		return nil, err
	}

	tmpl := &x509.Certificate{
		SerialNumber: serial,
		Subject: pkix.Name{
			CommonName:   "ocer-dns-resolver",
			Organization: []string{"OcerDNS Dev"},
		},
		NotBefore:             time.Now(),
		NotAfter:              time.Now().Add(365 * 24 * time.Hour),
		KeyUsage:              x509.KeyUsageDigitalSignature | x509.KeyUsageKeyEncipherment,
		ExtKeyUsage:           []x509.ExtKeyUsage{x509.ExtKeyUsageServerAuth},
		BasicConstraintsValid: true,
		IPAddresses:           []net.IP{net.ParseIP("127.0.0.1"), net.IPv6loopback},
		DNSNames:              []string{"localhost", "ocer-dns-resolver", "dns.test.com", "*.dns.test.com"},
	}

	certDER, err := x509.CreateCertificate(rand.Reader, tmpl, tmpl, &key.PublicKey, key)
	if err != nil {
		return nil, err
	}

	// 将临时证书写入文件，方便调试（同时写 DER 和 PEM 两种格式）
	certPath := "/tmp/ocer-dns-dev.crt"
	_ = os.WriteFile(certPath, certDER, 0644)

	pemPath := "/tmp/ocer-dns-dev.pem"
	pemBlock := &pem.Block{Type: "CERTIFICATE", Bytes: certDER}
	_ = os.WriteFile(pemPath, pem.EncodeToMemory(pemBlock), 0644)

	keyBytes, err := x509.MarshalECPrivateKey(key)
	if err != nil {
		return nil, err
	}
	_ = os.WriteFile("/tmp/ocer-dns-dev.key", keyBytes, 0600)

	log.Printf("tls: self-signed cert written to %s / %s (dev-only, do not use in production)", pemPath, certPath)

	// 将证书写入 PEM 格式，方便 kdig +tls-ca 使用
	_ = os.WriteFile("/tmp/ocer-dns-ca.pem", pem.EncodeToMemory(pemBlock), 0644)

	cert := tls.Certificate{
		Certificate: [][]byte{certDER},
		PrivateKey:  key,
	}

	return &cert, nil
}
