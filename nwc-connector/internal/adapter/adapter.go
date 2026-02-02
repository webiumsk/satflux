package adapter

import (
	"context"
)

// MakeInvoiceParams for NIP-47 make_invoice.
type MakeInvoiceParams struct {
	AmountSats   uint64 `json:"amount"`
	Description  string `json:"description"`
	DescriptionHash string `json:"description_hash,omitempty"`
	Expiry       *int   `json:"expiry,omitempty"`
}

// MakeInvoiceResult from backend.
type MakeInvoiceResult struct {
	PaymentRequest string `json:"invoice"`
	PaymentHash    string `json:"payment_hash"`
}

// LookupInvoiceParams for NIP-47 lookup_invoice (by payment_hash or invoice).
type LookupInvoiceParams struct {
	PaymentHash string `json:"payment_hash,omitempty"`
	Invoice     string `json:"invoice,omitempty"`
}

// LookupInvoiceResult from backend.
type LookupInvoiceResult struct {
	PaymentHash   string `json:"payment_hash"`
	PaymentRequest string `json:"invoice,omitempty"`
	Preimage     string `json:"preimage,omitempty"`
	Settled      bool   `json:"settled"`
}

// WalletAdapter abstracts the Lightning backend (LND, CLN, NWC).
type WalletAdapter interface {
	MakeInvoice(ctx context.Context, connectorID string, params MakeInvoiceParams) (*MakeInvoiceResult, error)
	LookupInvoice(ctx context.Context, connectorID string, params LookupInvoiceParams) (*LookupInvoiceResult, error)
}
