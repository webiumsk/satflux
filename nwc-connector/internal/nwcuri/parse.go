package nwcuri

import (
	"fmt"
	"net/url"
	"strings"
)

// BackendConfig holds decrypted merchant wallet NWC connection details (wallet = server, we = client).
type BackendConfig struct {
	WalletPubkey string `json:"wallet_pubkey"` // wallet's Nostr pubkey (hex)
	Relay        string `json:"relay"`         // relay URL (e.g. wss://...)
	Secret       string `json:"secret"`         // client secret (hex privkey) for talking to wallet
}

// ParseNWCURI parses a nostr+walletconnect URI into BackendConfig.
// Format: nostr+walletconnect:<pubkey>?relay=wss%3A%2F%2F...&secret=<hex>
func ParseNWCURI(uri string) (*BackendConfig, error) {
	uri = strings.TrimSpace(uri)
	const prefix = "nostr+walletconnect:"
	if !strings.HasPrefix(strings.ToLower(uri), prefix) {
		return nil, fmt.Errorf("invalid NWC URI: must start with %s", prefix)
	}
	rest := uri[len(prefix):]
	// rest = "<pubkey>?relay=...&secret=..." or "<pubkey>"
	idx := strings.Index(rest, "?")
	var pubkey string
	var query string
	if idx < 0 {
		pubkey = rest
	} else {
		pubkey = rest[:idx]
		query = rest[idx+1:]
	}
	pubkey = strings.TrimSpace(pubkey)
	if len(pubkey) != 64 {
		return nil, fmt.Errorf("invalid NWC URI: pubkey must be 64 hex chars, got %d", len(pubkey))
	}
	for _, c := range pubkey {
		if (c < '0' || c > '9') && (c < 'a' || c > 'f') && (c < 'A' || c > 'F') {
			return nil, fmt.Errorf("invalid NWC URI: pubkey must be hex")
		}
	}

	relay := ""
	secret := ""
	if query != "" {
		vals, err := url.ParseQuery(query)
		if err != nil {
			return nil, fmt.Errorf("invalid NWC URI query: %w", err)
		}
		relay = vals.Get("relay")
		secret = vals.Get("secret")
	}
	if relay == "" {
		return nil, fmt.Errorf("invalid NWC URI: relay is required")
	}
	relayDecoded, err := url.QueryUnescape(relay)
	if err != nil {
		relayDecoded = relay
	}
	if secret == "" {
		return nil, fmt.Errorf("invalid NWC URI: secret is required")
	}
	if len(secret) != 64 {
		return nil, fmt.Errorf("invalid NWC URI: secret must be 64 hex chars, got %d", len(secret))
	}
	for _, c := range secret {
		if (c < '0' || c > '9') && (c < 'a' || c > 'f') && (c < 'A' || c > 'F') {
			return nil, fmt.Errorf("invalid NWC URI: secret must be hex")
		}
	}

	return &BackendConfig{
		WalletPubkey: pubkey,
		Relay:        relayDecoded,
		Secret:       secret,
	}, nil
}
