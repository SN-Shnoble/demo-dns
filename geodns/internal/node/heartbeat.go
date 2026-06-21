package node

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"strings"
	"time"
)

// HeartbeatClient 上报节点心跳到 portal-web /api/v1/node/nodes/heartbeat
// 2026-06-22 改造：统一为纯 Token 鉴权，删除 HMAC 签名。
type HeartbeatClient struct {
	APIEndpoint string
	Bearer      string
	HTTPClient  *http.Client
}

func NewHeartbeatClient(apiEndpoint, bearer string, timeout time.Duration) *HeartbeatClient {
	return &HeartbeatClient{
		APIEndpoint: strings.TrimSuffix(apiEndpoint, "/"),
		Bearer:      bearer,
		HTTPClient:  &http.Client{Timeout: timeout},
	}
}

type HeartbeatPayload struct {
	Status               string `json:"status"`
	UptimeSeconds        int    `json:"uptime_seconds,omitempty"`
	Version              string `json:"version,omitempty"`
	CurrentConfigVersion int    `json:"current_config_version,omitempty"`
	ProfilesLoaded       int    `json:"profiles_loaded,omitempty"`
	LastConfigPullAt     string `json:"last_config_pull_at,omitempty"`
}

// Report 上报一次心跳
func (c *HeartbeatClient) Report(payload HeartbeatPayload) error {
	body, err := json.Marshal(payload)
	if err != nil {
		return fmt.Errorf("marshal heartbeat: %w", err)
	}

	url := c.APIEndpoint + "/api/v1/node/nodes/heartbeat"
	req, err := http.NewRequest(http.MethodPost, url, bytes.NewReader(body))
	if err != nil {
		return err
	}

	// 统一 Token 鉴权：Bearer
	if c.Bearer != "" {
		req.Header.Set("Authorization", "Bearer "+c.Bearer)
	}
	req.Header.Set("Content-Type", "application/json")

	resp, err := c.HTTPClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode >= 300 {
		respBody, _ := io.ReadAll(resp.Body)
		return fmt.Errorf("heartbeat %d: %s", resp.StatusCode, string(respBody))
	}
	return nil
}

// RunSchedule 按 interval 周期上报心跳
func (c *HeartbeatClient) RunSchedule(start time.Time, version string, interval time.Duration, onSuccess func(CurrentConfigVersion int)) {
	c.ReportWithStart(start, version, 0, onSuccess)
	ticker := time.NewTicker(interval)
	defer ticker.Stop()
	for range ticker.C {
		c.ReportWithStart(start, version, 0, onSuccess)
	}
}

func (c *HeartbeatClient) ReportWithStart(start time.Time, version string, profilesLoaded int, onSuccess func(currentConfigVersion int)) {
	uptime := int(time.Since(start).Seconds())
	if err := c.Report(HeartbeatPayload{
		Status:               "online",
		UptimeSeconds:        uptime,
		Version:              version,
		CurrentConfigVersion: 0,
		ProfilesLoaded:       profilesLoaded,
	}); err != nil {
		log.Printf("geodns: heartbeat report failed: %v", err)
		return
	}
	if onSuccess != nil {
		onSuccess(0)
	}
}
