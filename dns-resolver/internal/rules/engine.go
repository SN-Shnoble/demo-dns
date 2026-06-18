package rules

import "strings"

type Decision string

const (
	DecisionAllow Decision = "allow"
	DecisionBlock Decision = "block"
)

type Rule struct {
	RuleID           string `json:"rule_id"`
	ListType         string `json:"list_type"`
	MatchType        string `json:"match_type"`
	Domain           string `json:"domain"`
	NormalizedDomain string `json:"normalized_domain"`
	Action           string `json:"action"`
}

type Engine struct {
	allowExact  map[string]struct{}
	allowSuffix []string
	denyExact   map[string]struct{}
	denySuffix  []string
}

func New(ruleSet []Rule) *Engine {
	engine := &Engine{
		allowExact: map[string]struct{}{},
		denyExact:  map[string]struct{}{},
	}

	for _, rule := range ruleSet {
		normalized := rule.NormalizedDomain
		if normalized == "" {
			normalized = rule.Domain
		}
		if rule.ListType == "allow" {
			engine.addRule(normalized, rule.MatchType, true)
			continue
		}
		if rule.ListType == "deny" {
			engine.addRule(normalized, rule.MatchType, false)
		}
	}

	return engine
}

func (e *Engine) Decide(domain string) Decision {
	if _, ok := e.allowExact[domain]; ok || suffixMatch(domain, e.allowSuffix) {
		return DecisionAllow
	}
	if _, ok := e.denyExact[domain]; ok || suffixMatch(domain, e.denySuffix) {
		return DecisionBlock
	}
	return DecisionAllow
}

func (e *Engine) addRule(domain string, matchType string, allow bool) {
	switch matchType {
	case "suffix", "wildcard":
		if allow {
			e.allowSuffix = append(e.allowSuffix, strings.TrimPrefix(domain, "*."))
			return
		}
		e.denySuffix = append(e.denySuffix, strings.TrimPrefix(domain, "*."))
	default:
		if allow {
			e.allowExact[domain] = struct{}{}
			return
		}
		e.denyExact[domain] = struct{}{}
	}
}

func suffixMatch(domain string, suffixes []string) bool {
	for _, suffix := range suffixes {
		if domain == suffix || strings.HasSuffix(domain, "."+suffix) {
			return true
		}
	}
	return false
}
