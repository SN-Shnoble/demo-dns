package healthview

import (
	"context"
	"encoding/json"
	"net/http"
	"time"
)

type Node struct {
	NodeID             string   `json:"node_id"`
	Region             string   `json:"region"`
	Country            string   `json:"country"`
	City               string   `json:"city"`
	Status             string   `json:"status"`
	PublicIPv4         string   `json:"public_ipv4"`
	PublicIPv6         string   `json:"public_ipv6"`
	SupportedProtocols []string `json:"supported_protocols"`
	Weight             int      `json:"weight"`
	LastHeartbeatAt    string   `json:"last_heartbeat_at"`
}

// Client 用于 geodns → portal-web 拉取 health-view 数据。
// 2026-06-22 改造：统一为纯 Token 鉴权，删除 HMAC 签名。
// 使用 shared.token:internal 中间件要求的 X-Internal-Token 头。
type Client struct {
	BaseURL    string
	Token      string
	HTTPClient *http.Client
}

func (c Client) Fetch(ctx context.Context) (View, error) {
	client := c.HTTPClient
	if client == nil {
		client = &http.Client{Timeout: 5 * time.Second}
	}

	req, err := http.NewRequestWithContext(ctx, http.MethodGet, c.BaseURL, nil)
	if err != nil {
		return View{}, err
	}

	// 统一 Token 鉴权：Bearer + X-Internal-Token 双写，兼容不同中间件
	if c.Token != "" {
		req.Header.Set("Authorization", "Bearer "+c.Token)
		req.Header.Set("X-Internal-Token", c.Token)
	}

	resp, err := client.Do(req)
	if err != nil {
		return View{}, err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return View{}, &FetchError{Status: resp.StatusCode, URL: c.BaseURL}
	}

	var payload struct {
		Data View `json:"data"`
	}
	if err := json.NewDecoder(resp.Body).Decode(&payload); err != nil {
		return View{}, err
	}

	return payload.Data, nil
}

// FetchError 表示 health-view 拉取失败并保留了 HTTP 状态码，
// 方便日志和告警判断是鉴权失败（401/403）还是上游故障。
type FetchError struct {
	Status int
	URL    string
}

func (e *FetchError) Error() string {
	return "healthview: fetch " + e.URL + " returned status " + http.StatusText(e.Status)
}
