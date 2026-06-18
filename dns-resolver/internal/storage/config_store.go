package storage

import (
	"encoding/json"
	"os"
	"path/filepath"

	"ocer-dns/dns-resolver/internal/config"
)

type ConfigStore struct {
	ActivePath   string
	PreviousPath string
}

func (s ConfigStore) Apply(bundle []byte) error {
	if err := os.MkdirAll(filepath.Dir(s.ActivePath), 0o755); err != nil {
		return err
	}

	if current, err := os.ReadFile(s.ActivePath); err == nil {
		if err := os.WriteFile(s.PreviousPath, current, 0o600); err != nil {
			return err
		}
	}

	tmpPath := s.ActivePath + ".tmp"
	if err := os.WriteFile(tmpPath, bundle, 0o600); err != nil {
		return err
	}

	return os.Rename(tmpPath, s.ActivePath)
}

func (s ConfigStore) Load() (config.ResolverConfig, error) {
	var cfg config.ResolverConfig

	body, err := os.ReadFile(s.ActivePath)
	if err != nil {
		return cfg, err
	}

	if err := json.Unmarshal(body, &cfg); err != nil {
		return cfg, err
	}

	return cfg, nil
}
