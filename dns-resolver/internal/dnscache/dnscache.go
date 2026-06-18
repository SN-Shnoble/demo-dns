// Package dnscache provides a local in-memory DNS response cache.
// It implements LRU eviction and respects DNS TTL values.
// This cache is designed for full autonomy - no external dependencies.
package dnscache

import (
	"container/list"
	"context"
	"sync"
	"time"

	"github.com/miekg/dns"
)

// CacheEntry represents a cached DNS response
type CacheEntry struct {
	Key     string
	Msg     *dns.Msg
	Expires time.Time
	TTL     uint32
}

// DNSCache is a thread-safe LRU cache for DNS responses
type DNSCache struct {
	mu      sync.RWMutex
	entries map[string]*list.Element
	lru     *list.List
	maxSize int
	maxTTL  time.Duration
	enabled bool
	hits    uint64
	misses  uint64
}

// entry is the internal LRU list element
type entry struct {
	Key   string
	Value *CacheEntry
}

// New creates a new DNS cache with the specified max size and default TTL
func New(maxSize int, maxTTL time.Duration) *DNSCache {
	return &DNSCache{
		entries: make(map[string]*list.Element, maxSize),
		lru:     list.New(),
		maxSize: maxSize,
		maxTTL:  maxTTL,
		enabled: true,
	}
}

// SetEnabled enables or disables the cache
func (c *DNSCache) SetEnabled(enabled bool) {
	c.mu.Lock()
	c.enabled = enabled
	c.mu.Unlock()
}

// Enabled returns whether the cache is enabled
func (c *DNSCache) Enabled() bool {
	c.mu.RLock()
	defer c.mu.RUnlock()
	return c.enabled
}

// Get retrieves a cached DNS response for the given key (domain + qtype + profile)
func (c *DNSCache) Get(ctx context.Context, key string) (*dns.Msg, bool) {
	if !c.enabled {
		return nil, false
	}

	c.mu.Lock()
	defer c.mu.Unlock()

	elem, exists := c.entries[key]
	if !exists {
		c.misses++
		return nil, false
	}

	e := elem.Value.(*entry)
	if time.Now().After(e.Value.Expires) {
		// Entry expired
		c.lru.Remove(elem)
		delete(c.entries, key)
		c.misses++
		return nil, false
	}

	// Move to front (most recently used)
	c.lru.MoveToFront(elem)
	c.hits++
	return e.Value.Msg, true
}

// Set stores a DNS response in the cache
func (c *DNSCache) Set(ctx context.Context, key string, msg *dns.Msg) {
	if !c.enabled {
		return
	}

	// Calculate TTL from response
	ttl := c.extractTTL(msg)

	c.mu.Lock()
	defer c.mu.Unlock()

	// Check if entry already exists
	if elem, exists := c.entries[key]; exists {
		c.lru.Remove(elem)
		delete(c.entries, key)
	}

	// Evict oldest entries if at capacity
	for c.lru.Len() >= c.maxSize {
		oldest := c.lru.Back()
		if oldest != nil {
			e := oldest.Value.(*entry)
			delete(c.entries, e.Key)
			c.lru.Remove(oldest)
		}
	}

	// Calculate expiry time
	expires := time.Now().Add(time.Duration(ttl) * time.Second)
	if expires.IsZero() || ttl == 0 {
		expires = time.Now().Add(c.maxTTL)
	}

	// Add new entry
	e := &entry{
		Key: key,
		Value: &CacheEntry{
			Key:     key,
			Msg:     msg,
			Expires: expires,
			TTL:     ttl,
		},
	}
	elem := c.lru.PushFront(e)
	c.entries[key] = elem
}

// extractTTL extracts the minimum TTL from DNS answer records
func (c *DNSCache) extractTTL(msg *dns.Msg) uint32 {
	if msg == nil {
		return 0
	}

	minTTL := uint32(0)

	for _, rr := range msg.Answer {
		if ttl := rr.Header().Ttl; ttl > 0 {
			if minTTL == 0 || ttl < minTTL {
				minTTL = ttl
			}
		}
	}

	// If no answer TTL, check additional
	if minTTL == 0 {
		for _, rr := range msg.Extra {
			if ttl := rr.Header().Ttl; ttl > 0 {
				if minTTL == 0 || ttl < minTTL {
					minTTL = ttl
				}
			}
		}
	}

	// Default TTL if none found
	if minTTL == 0 {
		minTTL = 60 // 1 minute default
	}

	// Ensure minimum TTL of 5 seconds to avoid immediate expiration
	// (some upstream DNS returns TTL=1 for testing/load balancing)
	if minTTL < 5 {
		minTTL = 5
	}

	// Cap at max TTL
	maxTTL := uint32(c.maxTTL.Seconds())
	if minTTL > maxTTL {
		minTTL = maxTTL
	}

	return minTTL
}

// MakeKey creates a cache key from domain and query type
func MakeKey(domain string, qtype uint16, profileID string) string {
	return profileID + "|" + domain + "|" + dns.TypeToString[qtype]
}

// Stats returns cache statistics
type Stats struct {
	Hits    uint64
	Misses  uint64
	HitRate float64
	Size    int
	MaxSize int
	MaxTTL  time.Duration
	Enabled bool
}

func (c *DNSCache) Stats() Stats {
	c.mu.RLock()
	defer c.mu.RUnlock()

	total := c.hits + c.misses
	hitRate := float64(0)
	if total > 0 {
		hitRate = float64(c.hits) / float64(total) * 100
	}

	return Stats{
		Hits:    c.hits,
		Misses:  c.misses,
		HitRate: hitRate,
		Size:    c.lru.Len(),
		MaxSize: c.maxSize,
		MaxTTL:  c.maxTTL,
		Enabled: c.enabled,
	}
}

// Clear removes all entries from the cache
func (c *DNSCache) Clear() {
	c.mu.Lock()
	defer c.mu.Unlock()

	c.entries = make(map[string]*list.Element)
	c.lru = list.New()
}

// ResetStats resets hit/miss counters
func (c *DNSCache) ResetStats() {
	c.mu.Lock()
	defer c.mu.Unlock()

	c.hits = 0
	c.misses = 0
}
