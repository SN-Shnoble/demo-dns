package node

import (
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"strings"
	"time"
)

// Client 用于 geodns → portal-web 拉取 GeoDNS 配置。
// 2026-06-22 改造：统一为纯 Token 鉴权，删除 HMAC 签名。
// 2026-06-21 改造：优先从 api_key 文件读取鉴权，fallback 到内存中的 token。
type Client struct {
	Token       string
	APIEndpoint string
	APIKeyPath  string // 2026-06-21: register 时签发的 api_key 缓存文件路径
	client      *http.Client
}

type ResolverNode struct {
	NodeCode   string `json:"node_code"`
	Region     string `json:"region"`
	Country    string `json:"country"`
	City       string `json:"city"`
	PublicIPv4 string `json:"public_ipv4"`
	PublicIPv6 string `json:"public_ipv6"`
	Weight     int    `json:"weight"`
}

type GeoDNSConfig struct {
	Resolvers   []ResolverNode `json:"resolvers"`
	Domains     []string       `json:"domains"`
	GeneratedAt string         `json:"generated_at"`
	TTLSeconds  int            `json:"ttl_seconds"`
}

type ConfigResponse struct {
	Data GeoDNSConfig `json:"data"`
}

func NewClient(token, endpoint string) *Client {
	return &Client{
		Token:       token,
		APIEndpoint: strings.TrimSuffix(endpoint, "/"),
		client: &http.Client{
			Timeout: 10 * time.Second,
		},
	}
}

// NewClientWithAPIKeyPath 创建带 api_key 文件路径的 client（2026-06-21 新增）
func NewClientWithAPIKeyPath(token, endpoint, apiKeyPath string) *Client {
	c := NewClient(token, endpoint)
	c.APIKeyPath = apiKeyPath
	return c
}

// loadAPIKey 优先从独立文件读 api_key，fallback 到 token
func (c *Client) loadAPIKey() string {
	if c.APIKeyPath != "" {
		if data, err := os.ReadFile(c.APIKeyPath); err == nil {
			key := strings.TrimSpace(string(data))
			if key != "" {
				return key
			}
		}
	}
	return c.Token
}

func (c *Client) GetConfig() (*GeoDNSConfig, error) {
	url := c.APIEndpoint + "/node/geodns/config"
	req, err := http.NewRequest(http.MethodGet, url, nil)
	if err != nil {
		return nil, err
	}

	// 2026-06-21: 优先用 api_key 文件，fallback 到 token
	if key := c.loadAPIKey(); key != "" {
		req.Header.Set("Authorization", "Bearer "+key)
	}

	resp, err := c.client.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		body, _ := io.ReadAll(resp.Body)
		return nil, fmt.Errorf("config request failed: %d %s", resp.StatusCode, string(body))
	}

	var result ConfigResponse
	if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
		return nil, err
	}

	return &result.Data, nil
}

func (c *Client) RunConfigRefresh(ctx chan<- *GeoDNSConfig, refreshInterval time.Duration) {
	ticker := time.NewTicker(refreshInterval)
	defer ticker.Stop()

	c.fetchAndSend(ctx)

	for {
		select {
		case <-ticker.C:
			c.fetchAndSend(ctx)
		}
	}
}

func (c *Client) fetchAndSend(ctx chan<- *GeoDNSConfig) {
	config, err := c.GetConfig()
	if err != nil {
		log.Printf("geodns: failed to fetch config: %v", err)
		return
	}
	ctx <- config
	log.Printf("geodns: config refreshed: %d resolvers, %d domains", len(config.Resolvers), len(config.Domains))
}
