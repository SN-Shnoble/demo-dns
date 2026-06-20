package node

import (
"crypto/rand"
"encoding/hex"
"encoding/json"
"fmt"
"io"
"log"
"net/http"
"strconv"
"strings"
"time"
)

type Client struct {
	Token      string
	APIEndpoint string
	client     *http.Client
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
		Token:      token,
		APIEndpoint: strings.TrimSuffix(endpoint, "/"),
		client: &http.Client{
			Timeout: 10 * time.Second,
		},
	}
}

func (c *Client) GetConfig() (*GeoDNSConfig, error) {
	url := c.APIEndpoint + "/node/geodns/config"
	req, err := http.NewRequest(http.MethodGet, url, nil)
	if err != nil {
		return nil, err
	}

	c.signRequest(req)

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

func (c *Client) signRequest(req *http.Request) {
	req.Header.Set("Authorization", "Bearer "+c.Token)

	ts := strconv.FormatInt(time.Now().Unix(), 10)
	req.Header.Set("X-Timestamp", ts)

	nonce := make([]byte, 16)
	if _, err := io.ReadFull(rand.Reader, nonce); err == nil {
		req.Header.Set("X-Nonce", hex.EncodeToString(nonce))
	}
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
