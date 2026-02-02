package nostr

import (
	"context"
	"log"

	"github.com/nbd-wtf/go-nostr"
	"github.com/satflux/nwc-connector/internal/crypto"
	"github.com/satflux/nwc-connector/internal/db"
)

const kindNWCInfo = 13194

// NIP-47: Wallet advertises capabilities via replaceable kind 13194. Content is space-separated methods.
const nwcInfoContent = "get_info make_invoice lookup_invoice list_transactions"

// PublishInfoEvents publishes kind 13194 (info) for each active connector so clients (e.g. BTCPay) can discover capabilities.
func PublishInfoEvents(ctx context.Context, relay *nostr.Relay, connectors []db.Connector, masterKeyHex string) {
	for i := range connectors {
		conn := &connectors[i]
		secret, err := crypto.DecryptSecret(conn.NostrSecretEncrypted, masterKeyHex)
		if err != nil {
			log.Printf("nostr info: decrypt secret for connector %s: %v", conn.ID.String()[:8], err)
			continue
		}
		ev := &nostr.Event{
			Kind:      kindNWCInfo,
			Content:   nwcInfoContent,
			CreatedAt: nostr.Now(),
		}
		if err := ev.Sign(secret); err != nil {
			log.Printf("nostr info: sign for connector %s: %v", conn.ID.String()[:8], err)
			continue
		}
		if err := relay.Publish(ctx, *ev); err != nil {
			log.Printf("nostr info: publish for connector %s: %v", conn.ID.String()[:8], err)
			continue
		}
		log.Printf("nostr info: published kind 13194 for connector %s", conn.ID.String()[:8])
	}
}
