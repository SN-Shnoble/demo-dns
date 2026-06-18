package profile

import (
	"errors"
	"net"
	"strings"
)

type Resolver struct {
	SourceIPProfiles map[string]string
}

func New(sourceIPProfiles map[string]string) *Resolver {
	return &Resolver{SourceIPProfiles: sourceIPProfiles}
}

func (r *Resolver) ResolveDoHPath(path string) (string, error) {
	trimmed := strings.Trim(path, "/")
	if trimmed == "" {
		return "", errors.New("empty path")
	}

	parts := strings.Split(trimmed, "/")
	if len(parts) == 2 && parts[0] == "dns-query" && parts[1] != "" {
		return parts[1], nil
	}
	if len(parts) == 2 && parts[1] == "dns-query" && parts[0] != "" {
		return parts[0], nil
	}

	return "", errors.New("profile id not found in doh path")
}

func (r *Resolver) ResolveSourceIP(addr string) (string, error) {
	host, _, err := net.SplitHostPort(addr)
	if err != nil {
		host = addr
	}

	profileID, ok := r.SourceIPProfiles[host]
	if !ok {
		return "", errors.New("profile id not found for source ip")
	}

	return profileID, nil
}
