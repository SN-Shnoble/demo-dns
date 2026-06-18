// Package logging 实现 DNS 查询日志的本地缓冲与批量上报。
// 凭据完全来自 dns-resolver 启动时由 console 预签发的 APIKey / Secret，
// 不再从磁盘 identity 文件读取任何信息。
package logging

import (
	"bytes"
	"context"
	"crypto/hmac"
	"crypto/rand"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"sync"
	"time"
)

type LogEntry struct {
	ProfileUID     string `json:"profile_id"`
	DeviceUID      string `json:"device_id"`
	Domain         string `json:"query_name"`
	Action         string `json:"action"`
	Reason         string `json:"reason"`
	Category       string `json:"category"`
	ClientIP       string `json:"client_ip"`
	QueryType      string `json:"query_type"`
	ResponseCode   int    `json:"rcode"`
	ResponseTimeMs int64  `json:"latency_ms"`
	QueriedAt      int64  `json:"queried_at"`
}

// Credentials 是 console 预签发凭据在日志上报场景下的最小投影。
type Credentials struct {
	NodeID string
	APIKey string
	Secret string
}

type Buffer struct {
	mu       sync.Mutex
	entries  []LogEntry
	maxSize  int
	bufPath  string
	cpURL    string
	client   *http.Client
	flushInt time.Duration
	cred     Credentials
	onFlush  func(time.Time)
}

// NewBuffer 构造一个日志缓冲器，调用方必须传入已校验的控制面凭据。
// 任何凭据字段为空都会返回 nil，调用方应直接拒绝启动。
func NewBuffer(bufPath, cpURL string, maxSize int, flushInterval time.Duration, cred Credentials, onFlush func(time.Time)) *Buffer {
	if cred.NodeID == "" || cred.APIKey == "" || cred.Secret == "" {
		log.Printf("log buffer disabled: control plane credentials are missing")
		return nil
	}

	b := &Buffer{
		entries:  make([]LogEntry, 0, 1000),
		maxSize:  maxSize,
		bufPath:  bufPath,
		cpURL:    cpURL,
		flushInt: flushInterval,
		cred:     cred,
		onFlush:  onFlush,
		client: &http.Client{
			Timeout: 10 * time.Second,
		},
	}

	b.replayBuffer()

	return b
}

func (b *Buffer) Append(entry LogEntry) {
	if b == nil {
		return
	}
	b.mu.Lock()
	defer b.mu.Unlock()

	b.entries = append(b.entries, entry)
	if len(b.entries) >= b.maxSize {
		go b.Flush()
	}
}

func (b *Buffer) StartFlusher(ctx context.Context) {
	if b == nil {
		return
	}
	ticker := time.NewTicker(b.flushInt)
	defer ticker.Stop()

	for {
		select {
		case <-ticker.C:
			b.Flush()
		case <-ctx.Done():
			b.Flush()
			return
		}
	}
}

func (b *Buffer) Flush() {
	if b == nil {
		return
	}
	b.mu.Lock()
	if len(b.entries) == 0 {
		b.mu.Unlock()
		return
	}

	batch := append([]LogEntry(nil), b.entries...)
	b.entries = make([]LogEntry, 0, 1000)
	b.mu.Unlock()

	if err := b.sendBatch(batch); err != nil {
		log.Printf("Failed to send log batch: %v (writing to local buffer)", err)
		b.writeToDisk(batch)
		return
	}

	if b.onFlush != nil {
		b.onFlush(time.Now().UTC())
	}
}

func (b *Buffer) sendBatch(batch []LogEntry) error {
	payload := map[string]any{
		"batch_id": fmt.Sprintf("batch_%d", time.Now().UnixNano()),
		"node_id":  b.cred.NodeID,
		"sent_at":  time.Now().UTC().Format(time.RFC3339),
		"items":    batch,
	}

	body, err := json.Marshal(payload)
	if err != nil {
		return fmt.Errorf("marshal log batch: %w", err)
	}

	req, err := http.NewRequest(http.MethodPost, b.cpURL, bytes.NewReader(body))
	if err != nil {
		return fmt.Errorf("create request: %w", err)
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", "Bearer "+b.cred.APIKey)
	req.Header.Set("X-Hmac-Key", b.cred.Secret)

	ts := fmt.Sprintf("%d", time.Now().Unix())
	bodyHash := sha256.Sum256(body)
	canonical := ts + "\n" + req.Method + "\n" + req.URL.Path + "\n" + hex.EncodeToString(bodyHash[:])
	mac := hmac.New(sha256.New, []byte(b.cred.Secret))
	mac.Write([]byte(canonical))
	req.Header.Set("X-Signature", hex.EncodeToString(mac.Sum(nil)))
	req.Header.Set("X-Timestamp", ts)

	nonce := make([]byte, 16)
	if _, err := io.ReadFull(rand.Reader, nonce); err != nil {
		return fmt.Errorf("read nonce: %w", err)
	}
	req.Header.Set("X-Nonce", hex.EncodeToString(nonce))

	resp, err := b.client.Do(req)
	if err != nil {
		return fmt.Errorf("http post: %w", err)
	}
	defer resp.Body.Close()

	_, _ = io.Copy(io.Discard, resp.Body)
	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("http status %d", resp.StatusCode)
	}

	return nil
}

func (b *Buffer) writeToDisk(batch []LogEntry) {
	if err := os.MkdirAll(b.bufPath, 0o755); err != nil {
		log.Printf("Failed to create log buffer dir: %v", err)
		return
	}

	filename := filepath.Join(b.bufPath, fmt.Sprintf("query-log-%d.jsonl", time.Now().UnixNano()))
	file, err := os.OpenFile(filename, os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0o644)
	if err != nil {
		log.Printf("Failed to open log buffer file: %v", err)
		return
	}
	defer file.Close()

	encoder := json.NewEncoder(file)
	for _, entry := range batch {
		if err := encoder.Encode(entry); err != nil {
			log.Printf("Failed to write log entry to disk: %v", err)
		}
	}
}

func (b *Buffer) replayBuffer() {
	files, err := filepath.Glob(filepath.Join(b.bufPath, "query-log-*.jsonl"))
	if err != nil {
		return
	}

	for _, file := range files {
		data, err := os.ReadFile(file)
		if err != nil {
			log.Printf("Failed to read buffer file %s: %v", file, err)
			continue
		}

		var entries []LogEntry
		for _, line := range bytes.Split(bytes.TrimSpace(data), []byte("\n")) {
			if len(line) == 0 {
				continue
			}
			var entry LogEntry
			if err := json.Unmarshal(line, &entry); err != nil {
				continue
			}
			entries = append(entries, entry)
		}

		if len(entries) == 0 {
			_ = os.Remove(file)
			continue
		}

		if err := b.sendBatch(entries); err != nil {
			log.Printf("Failed to replay buffer file %s: %v (will retry)", file, err)
			return
		}

		_ = os.Remove(file)
		if b.onFlush != nil {
			b.onFlush(time.Now().UTC())
		}
	}
}
