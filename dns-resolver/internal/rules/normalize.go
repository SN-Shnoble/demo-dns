package rules

import (
	"errors"
	"fmt"
	"strings"
	"unicode/utf8"
)

func NormalizeDomain(domain string) (string, error) {
	value := strings.TrimSpace(domain)
	value = strings.TrimSuffix(value, ".")
	value = strings.ToLower(value)
	if value == "" {
		return "", errors.New("domain must not be empty")
	}
	if len(value) > 253 {
		return "", errors.New("domain too long")
	}

	if !utf8.ValidString(value) {
		return "", errors.New("domain is not valid utf-8")
	}

	labels := strings.Split(value, ".")
	for _, label := range labels {
		if label == "" || len(label) > 63 {
			return "", errors.New("invalid label length")
		}
		for _, r := range label {
			if (r >= 'a' && r <= 'z') || (r >= '0' && r <= '9') || r == '-' || r == '*' {
				continue
			}
			return "", fmt.Errorf("label contains unsupported character %q", r)
		}
	}

	return value, nil
}
