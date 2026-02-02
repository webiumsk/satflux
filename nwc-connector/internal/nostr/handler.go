package nostr

import (
	"context"
	"encoding/json"
	"log"

	"github.com/nbd-wtf/go-nostr"
	"github.com/nbd-wtf/go-nostr/nip04"
	"github.com/satflux/nwc-connector/internal/adapter"
	"github.com/satflux/nwc-connector/internal/crypto"
	"github.com/satflux/nwc-connector/internal/db"
	"github.com/satflux/nwc-connector/internal/policy"
)

const kindNWCResponse = 23195

// NWC request payload (decrypted).
type nwcRequest struct {
	Method string          `json:"method"`
	Params json.RawMessage `json:"params"`
}

// NWC response result (encrypted in kind 23195).
type nwcResponse struct {
	ResultType string      `json:"result_type"`
	Result     interface{} `json:"result,omitempty"`
	Error      *nwcError   `json:"error,omitempty"`
}

type nwcError struct {
	Code    int    `json:"code"`
	Message string `json:"message"`
}

// HandleRequest decrypts the NIP-47 request, enforces policy, calls adapter, and returns the response event (kind 23195) to publish.
func HandleRequest(
	ctx context.Context,
	ev *nostr.Event,
	conn *db.Connector,
	masterKeyHex string,
	wallet adapter.WalletAdapter,
) (*nostr.Event, error) {
	ourSecret, decErr := crypto.DecryptSecret(conn.NostrSecretEncrypted, masterKeyHex)
	if decErr != nil {
		return nil, decErr
	}
	sharedSecret, decErr := nip04.ComputeSharedSecret(ev.PubKey, ourSecret)
	if decErr != nil {
		return nil, decErr
	}
	plain, decErr := nip04.Decrypt(ev.Content, sharedSecret)
	if decErr != nil {
		return nil, decErr
	}

	var req nwcRequest
	if err := json.Unmarshal([]byte(plain), &req); err != nil {
		return buildErrorEvent(ev.PubKey, ourSecret, -32600, "invalid request")
	}

	if !policy.Allow(req.Method) {
		return buildErrorEvent(ev.PubKey, ourSecret, -32601, "method not allowed: "+req.Method)
	}

	var result interface{}
	var resErr *nwcError

	switch req.Method {
	case "make_invoice":
		var params adapter.MakeInvoiceParams
		_ = json.Unmarshal(req.Params, &params)
		res, aErr := wallet.MakeInvoice(ctx, conn.ID.String(), params)
		if aErr != nil {
			resErr = &nwcError{Code: -32000, Message: aErr.Error()}
		} else {
			result = res
		}
	case "lookup_invoice":
		var params adapter.LookupInvoiceParams
		_ = json.Unmarshal(req.Params, &params)
		res, aErr := wallet.LookupInvoice(ctx, conn.ID.String(), params)
		if aErr != nil {
			resErr = &nwcError{Code: -32000, Message: aErr.Error()}
		} else {
			result = res
		}
	case "get_info":
		result = map[string]interface{}{"alias": "nwc-connector", "network": "bitcoin"}
	case "list_transactions":
		// Receive-only: return empty list so BTCPay "Test connection" sees the command as available
		result = map[string]interface{}{"transactions": []interface{}{}}
	default:
		resErr = &nwcError{Code: -32601, Message: "method not allowed"}
	}

	resp := nwcResponse{ResultType: req.Method}
	if resErr != nil {
		resp.Error = resErr
	} else {
		resp.Result = result
	}
	respJSON, _ := json.Marshal(resp)
	encrypted, encErr := nip04.Encrypt(string(respJSON), sharedSecret)
	if encErr != nil {
		return nil, encErr
	}

	respEv := &nostr.Event{
		Kind:    kindNWCResponse,
		Tags:    nostr.Tags{nostr.Tag{"p", ev.PubKey}},
		Content: encrypted,
	}
	if err := respEv.Sign(ourSecret); err != nil {
		return nil, err
	}
	return respEv, nil
}

func buildErrorEvent(clientPubkey, ourSecret string, code int, msg string) (*nostr.Event, error) {
	sharedSecret, err := nip04.ComputeSharedSecret(clientPubkey, ourSecret)
	if err != nil {
		log.Printf("nip04 ComputeSharedSecret error: %v", err)
		return nil, err
	}
	resp := nwcResponse{
		ResultType: "error",
		Error:      &nwcError{Code: code, Message: msg},
	}
	respJSON, _ := json.Marshal(resp)
	encrypted, err := nip04.Encrypt(string(respJSON), sharedSecret)
	if err != nil {
		log.Printf("nip04 encrypt error: %v", err)
		return nil, err
	}
	ev := &nostr.Event{
		Kind:    kindNWCResponse,
		Tags:    nostr.Tags{nostr.Tag{"p", clientPubkey}},
		Content: encrypted,
	}
	if err := ev.Sign(ourSecret); err != nil {
		return nil, err
	}
	return ev, nil
}
