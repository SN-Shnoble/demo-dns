package resolver

import (
	"log"
	"net"
	"strings"

	"ocer-dns/dns-resolver/internal/matching"
)

// ResolutionContext holds the full context of a DNS query resolution.
type ResolutionContext struct {
	ProfileUID string
	DeviceUID  string
	DeviceType string
	ClientIP   net.IP
	Domain     string
	QueryType  string
	Protocol   string // "doh", "dot", "udp"
}

// ProfileResolutionLayer handles the complete resolution pipeline:
// Profile identification → Device identification → Policy loading → Decision
type ProfileResolutionLayer struct {
	engine *matching.Engine
}

// New creates a new ProfileResolutionLayer.
func New(engine *matching.Engine) *ProfileResolutionLayer {
	return &ProfileResolutionLayer{
		engine: engine,
	}
}

// Resolve runs the full resolution pipeline for a DNS query.
func (prl *ProfileResolutionLayer) Resolve(ctx *ResolutionContext) *matching.Decision {
	// Apply rule matching
	decision := prl.engine.Match(ctx.Domain)

	log.Printf("[RESOLVER] profile=%s device=%s domain=%s action=%s reason=%s",
		ctx.ProfileUID, ctx.DeviceUID, ctx.Domain, decision.Action, decision.Reason)

	return decision
}

// ExtractProfileFromPath extracts the profile UID from a DoH URL path.
// Format: /{profile_uid}/dns-query
func ExtractProfileFromPath(path string) string {
	path = strings.TrimPrefix(path, "/")
	path = strings.TrimSuffix(path, "/dns-query")

	if len(path) == 0 || len(path) > 32 {
		return ""
	}

	// Validate it looks like a profile UID (alphanumeric)
	for _, c := range path {
		if !((c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z') || (c >= '0' && c <= '9')) {
			return ""
		}
	}

	return path
}

// ExtractDeviceFromHeaders extracts device information from HTTP headers.
func ExtractDeviceFromHeaders(headers map[string]string) (deviceUID string, deviceType string) {
	deviceUID = headers["X-Device-ID"]
	deviceType = headers["X-Device-Type"]
	return
}

// ExtractProfileFromSNI extracts the profile UID from a TLS SNI.
// Format: {profile_uid}.dns.example.com
func ExtractProfileFromSNI(sni string) string {
	parts := strings.SplitN(sni, ".", 2)
	if len(parts) < 2 {
		return ""
	}
	if len(parts[0]) == 32 {
		return parts[0]
	}
	return ""
}
