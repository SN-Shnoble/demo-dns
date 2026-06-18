package healthview

import "time"

type View struct {
	GeneratedAt time.Time `json:"generated_at"`
	TTLSeconds  int       `json:"ttl_seconds"`
	Nodes       []Node    `json:"nodes"`
}
