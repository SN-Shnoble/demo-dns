package main

import (
	"context"
	"errors"
	"flag"
	"log"
	"os"
	"os/signal"
	"syscall"

	"ocer-dns/geodns/internal/config"
	"ocer-dns/geodns/internal/server"
)

func main() {
	configPath := flag.String("config", defaultConfigPath(), "path to geodns config yaml")
	flag.Parse()

	cfg, err := config.Load(*configPath)
	if err != nil {
		log.Fatalf("geodns: failed to load config %s: %v", *configPath, err)
	}

	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()

	log.Print("starting geodns")
	svc := server.New(cfg)
	if err := svc.Run(ctx); err != nil && !errors.Is(err, context.Canceled) {
		log.Fatal(err)
	}
}

func defaultConfigPath() string {
	if path := os.Getenv("GEODNS_CONFIG"); path != "" {
		return path
	}
	return "configs/config.example.yaml"
}
