package nostr

import (
	"context"
	"log"
	"time"

	"github.com/nbd-wtf/go-nostr"
	"github.com/satflux/nwc-connector/internal/adapter"
	"github.com/satflux/nwc-connector/internal/db"
)

const kindNWCRequest = 23194

// Listener subscribes to Nostr relay for NIP-47 request events (kind 23194) addressed to our connectors.
type Listener struct {
	relayURL     string
	db           *db.DB
	masterKeyHex string
	wallet       adapter.WalletAdapter
}

func NewListener(relayURL string, database *db.DB, masterKeyHex string, wallet adapter.WalletAdapter) *Listener {
	return &Listener{
		relayURL:     relayURL,
		db:           database,
		masterKeyHex: masterKeyHex,
		wallet:       wallet,
	}
}

// Run connects to the relay and subscribes to kind 23194 with #p = our pubkeys. Handles each request and publishes response (kind 23195).
func (l *Listener) Run(ctx context.Context) {
	for {
		select {
		case <-ctx.Done():
			return
		default:
			l.runOnce(ctx)
		}
		// Reconnect / re-poll after delay (e.g. when no connectors or relay disconnect)
		select {
		case <-ctx.Done():
			return
		case <-time.After(30 * time.Second):
		}
	}
}

func (l *Listener) runOnce(ctx context.Context) {
	pubkeys, err := l.db.ListActivePubkeys(ctx)
	if err != nil {
		log.Printf("nostr listener: list pubkeys: %v", err)
		return
	}
	if len(pubkeys) == 0 {
		return
	}

	relay, err := nostr.RelayConnect(ctx, l.relayURL)
	if err != nil {
		log.Printf("nostr listener: relay connect %s: %v", l.relayURL, err)
		return
	}
	defer relay.Close()

	// NIP-47: publish kind 13194 (info) so clients (e.g. BTCPay) can discover our capabilities
	connectors, err := l.db.ListActiveConnectors(ctx)
	if err != nil {
		log.Printf("nostr listener: list connectors: %v", err)
	} else {
		PublishInfoEvents(ctx, relay, connectors, l.masterKeyHex)
	}

	// NIP-47: client sends kind 23194; we are recipient so we filter by #p = our pubkeys
	filter := nostr.Filter{
		Kinds: []int{kindNWCRequest},
		Tags:  nostr.TagMap{"p": pubkeys},
	}
	sub, err := relay.Subscribe(ctx, []nostr.Filter{filter})
	if err != nil {
		log.Printf("nostr listener: subscribe: %v", err)
		return
	}
	defer sub.Unsub()

	log.Printf("nostr listener: subscribed on %s for %d connector(s)", l.relayURL, len(pubkeys))

	for {
		select {
		case <-ctx.Done():
			return
		case ev, ok := <-sub.Events:
			if !ok {
				return
			}
			l.handleEvent(ctx, relay, ev)
		}
	}
}

func (l *Listener) handleEvent(ctx context.Context, relay *nostr.Relay, ev *nostr.Event) {
	// Event is kind 23194; #p tag contains our pubkey (we are the wallet). Find which connector.
	var ourPubkey string
	for _, tag := range ev.Tags {
		if len(tag) >= 2 && tag[0] == "p" {
			ourPubkey = tag[1]
			break
		}
	}
	if ourPubkey == "" {
		log.Printf("nostr listener: event %s missing p tag", ev.ID)
		return
	}

	conn, err := l.db.GetConnectorByPubkey(ctx, ourPubkey)
	if err != nil || conn == nil {
		log.Printf("nostr listener: connector not found for p=%s", ourPubkey[:16]+"...")
		return
	}

	respEv, err := HandleRequest(ctx, ev, conn, l.masterKeyHex, l.wallet)
	if err != nil {
		log.Printf("nostr listener: handle request: %v", err)
		return
	}
	if respEv == nil {
		return
	}

	if err := relay.Publish(ctx, *respEv); err != nil {
		log.Printf("nostr listener: publish response: %v", err)
		return
	}
	log.Printf("nostr listener: responded to %s for connector %s", ev.ID[:16]+"...", conn.ID.String()[:8]+"...")
}
