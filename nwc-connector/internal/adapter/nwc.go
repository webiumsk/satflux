package adapter

import (
	"context"
	"encoding/json"
	"errors"
	"log"
	"time"

	"github.com/google/uuid"
	"github.com/nbd-wtf/go-nostr"
	"github.com/nbd-wtf/go-nostr/nip04"
	"github.com/satflux/nwc-connector/internal/crypto"
	"github.com/satflux/nwc-connector/internal/db"
)

const kindNWCRequest = 23194
const kindNWCResponse = 23195

// NwcClientAdapter implements WalletAdapter by forwarding make_invoice/lookup_invoice to the merchant's NWC wallet.
type NwcClientAdapter struct {
	database   *db.DB
	masterKey  string
}

// NewNwcClientAdapter returns an adapter that loads connector from DB and, when backend_type=nwc, calls merchant wallet via NIP-47.
func NewNwcClientAdapter(database *db.DB, masterKey string) *NwcClientAdapter {
	return &NwcClientAdapter{database: database, masterKey: masterKey}
}

type backendConfig struct {
	WalletPubkey string `json:"wallet_pubkey"`
	Relay        string `json:"relay"`
	Secret       string `json:"secret"`
}

func (a *NwcClientAdapter) getBackendConfig(ctx context.Context, connectorID string) (*db.Connector, *backendConfig, error) {
	id, err := uuid.Parse(connectorID)
	if err != nil {
		return nil, nil, err
	}
	conn, err := a.database.GetConnectorByID(ctx, id)
	if err != nil || conn == nil {
		return nil, nil, errors.New("connector not found")
	}
	if conn.BackendType != "nwc" || conn.BackendConfigEncrypted == nil || *conn.BackendConfigEncrypted == "" {
		return nil, nil, errors.New("lightning backend not configured: make_invoice not available")
	}
	plain, err := crypto.DecryptSecret(*conn.BackendConfigEncrypted, a.masterKey)
	if err != nil {
		return nil, nil, err
	}
	var cfg backendConfig
	if err := json.Unmarshal([]byte(plain), &cfg); err != nil {
		return nil, nil, err
	}
	if cfg.WalletPubkey == "" || cfg.Relay == "" || cfg.Secret == "" {
		return nil, nil, errors.New("invalid backend config")
	}
	return conn, &cfg, nil
}

// requestResponse sends a NIP-47 request to the merchant wallet relay and waits for the response.
func (a *NwcClientAdapter) requestResponse(ctx context.Context, cfg *backendConfig, method string, params interface{}) ([]byte, error) {
	ourPubkey, err := nostr.GetPublicKey(cfg.Secret)
	if err != nil {
		return nil, err
	}
	sharedSecret, err := nip04.ComputeSharedSecret(cfg.WalletPubkey, cfg.Secret)
	if err != nil {
		return nil, err
	}
	req := map[string]interface{}{"method": method, "params": params}
	reqJSON, _ := json.Marshal(req)
	encrypted, err := nip04.Encrypt(string(reqJSON), sharedSecret)
	if err != nil {
		return nil, err
	}
	ev := &nostr.Event{
		Kind:      kindNWCRequest,
		Content:   encrypted,
		Tags:      nostr.Tags{nostr.Tag{"p", cfg.WalletPubkey}},
		CreatedAt: nostr.Now(),
	}
	if err := ev.Sign(cfg.Secret); err != nil {
		return nil, err
	}

	relay, err := nostr.RelayConnect(ctx, cfg.Relay)
	if err != nil {
		return nil, err
	}
	defer relay.Close()

	// Subscribe for response before publishing so we don't miss it
	subCtx, cancel := context.WithTimeout(ctx, 30*time.Second)
	defer cancel()
	filter := nostr.Filter{
		Kinds: []int{kindNWCResponse},
		Tags:  nostr.TagMap{"p": []string{ourPubkey}, "e": []string{ev.ID}},
	}
	sub, err := relay.Subscribe(subCtx, []nostr.Filter{filter})
	if err != nil {
		return nil, err
	}
	defer sub.Unsub()

	if err := relay.Publish(ctx, *ev); err != nil {
		return nil, err
	}
	log.Printf("nwc client: published %s request to %s", method, cfg.Relay[:min(30, len(cfg.Relay))])

	select {
	case <-subCtx.Done():
		return nil, errors.New("timeout waiting for NWC response")
	case respEv, ok := <-sub.Events:
		if !ok {
			return nil, errors.New("relay closed before response")
		}
		plain, err := nip04.Decrypt(respEv.Content, sharedSecret)
		if err != nil {
			return nil, err
		}
		var nwcResp struct {
			ResultType string          `json:"result_type"`
			Result     json.RawMessage `json:"result,omitempty"`
			Error      *struct {
				Code    int    `json:"code"`
				Message string `json:"message"`
			} `json:"error,omitempty"`
		}
		if err := json.Unmarshal([]byte(plain), &nwcResp); err != nil {
			return nil, err
		}
		if nwcResp.Error != nil {
			return nil, errors.New(nwcResp.Error.Message)
		}
		return nwcResp.Result, nil
	}
}

func min(a, b int) int {
	if a < b {
		return a
	}
	return b
}

func (a *NwcClientAdapter) MakeInvoice(ctx context.Context, connectorID string, params MakeInvoiceParams) (*MakeInvoiceResult, error) {
	_, cfg, err := a.getBackendConfig(ctx, connectorID)
	if err != nil {
		return nil, err
	}
	pm := map[string]interface{}{
		"amount": params.AmountSats,
		"description": params.Description,
	}
	if params.DescriptionHash != "" {
		pm["description_hash"] = params.DescriptionHash
	}
	if params.Expiry != nil {
		pm["expiry"] = *params.Expiry
	}
	raw, err := a.requestResponse(ctx, cfg, "make_invoice", pm)
	if err != nil {
		return nil, err
	}
	var res MakeInvoiceResult
	if err := json.Unmarshal(raw, &res); err != nil {
		return nil, err
	}
	return &res, nil
}

func (a *NwcClientAdapter) LookupInvoice(ctx context.Context, connectorID string, params LookupInvoiceParams) (*LookupInvoiceResult, error) {
	_, cfg, err := a.getBackendConfig(ctx, connectorID)
	if err != nil {
		return nil, err
	}
	pm := map[string]interface{}{}
	if params.PaymentHash != "" {
		pm["payment_hash"] = params.PaymentHash
	}
	if params.Invoice != "" {
		pm["invoice"] = params.Invoice
	}
	raw, err := a.requestResponse(ctx, cfg, "lookup_invoice", pm)
	if err != nil {
		return nil, err
	}
	var res LookupInvoiceResult
	if err := json.Unmarshal(raw, &res); err != nil {
		return nil, err
	}
	return &res, nil
}
