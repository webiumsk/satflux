package main

import (
	"context"
	"log"
	"net/http"
	"os"
	"os/signal"
	"syscall"

	"github.com/satflux/nwc-connector/internal/adapter"
	"github.com/satflux/nwc-connector/internal/api"
	"github.com/satflux/nwc-connector/internal/config"
	"github.com/satflux/nwc-connector/internal/db"
	"github.com/satflux/nwc-connector/internal/nostr"
)

func main() {
	cfg := config.Load()
	if cfg.NWCMasterKey == "" {
		log.Print("warning: NWC_MASTER_KEY not set, connector creation will fail")
	}

	ctx := context.Background()
	database, err := db.New(ctx, cfg.DBURL)
	if err != nil {
		log.Fatalf("db: %v", err)
	}
	defer database.Close()

	srv := api.New(cfg, database)

	mux := http.NewServeMux()
	mux.HandleFunc("POST /connectors", srv.CreateConnector)
	mux.HandleFunc("GET /connectors/{id}", srv.GetConnector)
	mux.HandleFunc("POST /connectors/{id}/revoke", srv.RevokeConnector)
	mux.HandleFunc("GET /health", srv.Health)

	// Start Nostr listener in background (subscribe to relay for NIP-47 events)
	walletAdapter := adapter.NewNwcClientAdapter(database, cfg.NWCMasterKey)
	listener := nostr.NewListener(cfg.RelayURL, database, cfg.NWCMasterKey, walletAdapter)
	go listener.Run(ctx)

	httpServer := &http.Server{Addr: cfg.HTTPAddr, Handler: mux}
	go func() {
		log.Printf("nwc-connector listening on %s", cfg.HTTPAddr)
		if err := httpServer.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			log.Fatalf("http: %v", err)
		}
	}()

	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit
	log.Print("shutting down...")
	_ = httpServer.Shutdown(context.Background())
}
