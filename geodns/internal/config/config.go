package config

import (
	"os"
	"time"

	"gopkg.in/yaml.v3"
)

type Config struct {
	Server  ServerConfig  `yaml:"server"`
	Routing RoutingConfig `yaml:"routing"`
}

type ServerConfig struct {
	ListenAddr        string `yaml:"listen_addr"`
	ConsoleHealthURL  string `yaml:"console_health_url"`
	// ConsoleHealthToken 用于访问 portal-web /api/v1/internal/* 时携带的
	// shared token（X-Internal-Token）。中间件在 RequireSharedToken 校验，
	// 留空则视为匿名调用，几乎必定 401。
	ConsoleHealthToken string `yaml:"console_health_token"`
	RefreshInterval    string `yaml:"refresh_interval"`
	RequestTimeoutSec  int    `yaml:"request_timeout_seconds"`
}

type RoutingConfig struct {
	GlobalFallbackRegion string   `yaml:"global_fallback_region"`
	AllowedRegions      []string `yaml:"allowed_regions,omitempty"`
	// OverloadThreshold 已弃用：节点健康由 (now - last_heartbeat_at) 超时判断
	// 不再基于 QPS/CPU/MEM/DISK 任何"健康"信息做降权/过载判定
	OverloadThreshold float64 `yaml:"overload_threshold,omitempty"`
}

func (c *Config) RefreshDuration() time.Duration {
	if c.Server.RefreshInterval == "" {
		return 15 * time.Second
	}
	d, err := time.ParseDuration(c.Server.RefreshInterval)
	if err != nil || d <= 0 {
		return 15 * time.Second
	}
	return d
}

func (c *Config) RequestTimeout() time.Duration {
	if c.Server.RequestTimeoutSec <= 0 {
		return 5 * time.Second
	}
	return time.Duration(c.Server.RequestTimeoutSec) * time.Second
}

func (c *Config) GlobalFallback() string {
	if c.Routing.GlobalFallbackRegion == "" {
		return "global"
	}
	return c.Routing.GlobalFallbackRegion
}

// HealthViewToken 优先级：YAML 配置 > GEODNS_INTERNAL_TOKEN 环境变量。
// 容器化部署时建议把 INTERNAL_SHARED_TOKEN 注入成 GEODNS_INTERNAL_TOKEN，
// 避免把 token 写进镜像。
func (c *Config) HealthViewToken() string {
	if t := c.Server.ConsoleHealthToken; t != "" {
		return t
	}
	return os.Getenv("GEODNS_INTERNAL_TOKEN")
}

func Load(path string) (*Config, error) {
	data, err := os.ReadFile(path)
	if err != nil {
		return nil, err
	}

	cfg := &Config{}
	if err := yaml.Unmarshal(data, cfg); err != nil {
		return nil, err
	}

	if cfg.Server.ListenAddr == "" {
		cfg.Server.ListenAddr = ":5354"
	}
	return cfg, nil
}
