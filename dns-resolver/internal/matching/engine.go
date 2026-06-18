package matching

import (
	"strings"
	"sync"
)

// Decision represents the result of a DNS query match.
type Decision struct {
	Action   string `json:"action"`   // ALLOW / BLOCK / REWRITE / DROP
	Reason   string `json:"reason"`   // allowlist / denylist / security / parental / adblock
	Category string `json:"category"` // malware / phishing / adult / gambling / ads / ...
}

// Engine is the core rule matching engine with 8-level policy priority.
//
// Priority (highest → lowest):
//  1. Allow List (user-defined)
//  2. Deny List (user-defined)
//  3. Security - Malware
//  4. Security - Phishing
//  5. Security - Ransomware / Cryptojacking / Botnet C2
//  6. Parental Control (adult / gambling / violence / social_media / ...)
//  7. Ad Block (OISD / AdGuard / EasyList)
//  8. Default Allow
type Engine struct {
	mu sync.RWMutex

	// Level 1: Allow list
	allowExact map[string]bool
	allowTrie  *Trie

	// Level 2: Deny list
	denyExact map[string]bool
	denyTrie  *Trie

	// Level 3-5: Security categories
	securityCategories map[string]map[string]bool // category -> domain set

	// Level 6: Parental control categories
	parentalCategories map[string]map[string]bool // category -> domain set

	// Level 7: Ad block domain set
	adBlockDomains map[string]bool
	adBlockTrie    *Trie
}

// NewEngine creates a new rule matching engine.
func NewEngine() *Engine {
	return &Engine{
		allowExact:         make(map[string]bool),
		denyExact:          make(map[string]bool),
		allowTrie:          NewTrie(),
		denyTrie:           NewTrie(),
		securityCategories: make(map[string]map[string]bool),
		parentalCategories: make(map[string]map[string]bool),
		adBlockDomains:     make(map[string]bool),
		adBlockTrie:        NewTrie(),
	}
}

// LoadAllowRules replaces the allow rule set (Level 1).
func (e *Engine) LoadAllowRules(exact []string, wildcard []string) {
	e.mu.Lock()
	defer e.mu.Unlock()

	e.allowExact = make(map[string]bool)
	e.allowTrie = NewTrie()

	for _, domain := range exact {
		e.allowExact[normalizeDomain(domain)] = true
	}
	for _, domain := range wildcard {
		e.allowTrie.Insert(reverseDomain(normalizeDomain(domain)))
	}
}

// LoadDenyRules replaces the deny rule set (Level 2).
func (e *Engine) LoadDenyRules(exact []string, wildcard []string) {
	e.mu.Lock()
	defer e.mu.Unlock()

	e.denyExact = make(map[string]bool)
	e.denyTrie = NewTrie()

	for _, domain := range exact {
		e.denyExact[normalizeDomain(domain)] = true
	}
	for _, domain := range wildcard {
		e.denyTrie.Insert(reverseDomain(normalizeDomain(domain)))
	}
}

// LoadSecurityCategory loads a specific security category (Level 3-5).
func (e *Engine) LoadSecurityCategory(category string, domains []string) {
	e.mu.Lock()
	defer e.mu.Unlock()

	set := make(map[string]bool, len(domains))
	for _, d := range domains {
		set[normalizeDomain(d)] = true
	}
	e.securityCategories[category] = set
}

// LoadParentalCategory loads a parental control category (Level 6).
func (e *Engine) LoadParentalCategory(category string, domains []string) {
	e.mu.Lock()
	defer e.mu.Unlock()

	set := make(map[string]bool, len(domains))
	for _, d := range domains {
		set[normalizeDomain(d)] = true
	}
	e.parentalCategories[category] = set
}

// LoadAdBlockDomains loads the ad blocking domain set (Level 7).
func (e *Engine) LoadAdBlockDomains(exact []string, wildcard []string) {
	e.mu.Lock()
	defer e.mu.Unlock()

	e.adBlockDomains = make(map[string]bool)
	e.adBlockTrie = NewTrie()

	for _, domain := range exact {
		e.adBlockDomains[normalizeDomain(domain)] = true
	}
	for _, domain := range wildcard {
		e.adBlockTrie.Insert(reverseDomain(normalizeDomain(domain)))
	}
}

// Match checks a domain against all rule sets with the defined priority.
func (e *Engine) Match(domain string) *Decision {
	e.mu.RLock()
	defer e.mu.RUnlock()

	domain = normalizeDomain(domain)

	// Level 1: Allow list (highest priority)
	if e.matchExactSet(e.allowExact, domain) || e.allowTrie.Search(reverseDomain(domain)) {
		return &Decision{Action: "ALLOW", Reason: "allowlist"}
	}

	// Level 2: Deny list
	if e.matchExactSet(e.denyExact, domain) || e.denyTrie.Search(reverseDomain(domain)) {
		return &Decision{Action: "BLOCK", Reason: "denylist"}
	}

	// Level 3-5: Security categories
	for category, set := range e.securityCategories {
		if set[domain] {
			return &Decision{Action: "BLOCK", Reason: "security", Category: category}
		}
	}

	// Level 6: Parental control categories
	for category, set := range e.parentalCategories {
		if set[domain] {
			return &Decision{Action: "BLOCK", Reason: "parental", Category: category}
		}
	}

	// Level 7: Ad block
	if e.adBlockDomains[domain] || e.adBlockTrie.Search(reverseDomain(domain)) {
		return &Decision{Action: "BLOCK", Reason: "adblock", Category: "ads"}
	}

	// Level 8: Default allow
	return &Decision{Action: "ALLOW", Reason: "default"}
}

func (e *Engine) matchExactSet(set map[string]bool, domain string) bool {
	return set[domain]
}

// normalizeDomain normalizes a domain for matching.
func normalizeDomain(domain string) string {
	domain = strings.TrimSuffix(domain, ".")
	domain = strings.ToLower(domain)
	return strings.TrimPrefix(domain, "*.")
}

// reverseDomain reverses the labels of a domain for Trie matching.
// e.g., "example.com" -> "com.example"
func reverseDomain(domain string) string {
	domain = strings.TrimPrefix(domain, "*")
	parts := strings.Split(domain, ".")
	for i, j := 0, len(parts)-1; i < j; i, j = i+1, j-1 {
		parts[i], parts[j] = parts[j], parts[i]
	}
	return strings.Join(parts, ".")
}
