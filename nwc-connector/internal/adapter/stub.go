package adapter

import (
	"context"
	"errors"
)

// StubAdapter returns "not configured" for all calls. Use until LND/NWC backend is wired.
type StubAdapter struct{}

func NewStubAdapter() *StubAdapter {
	return &StubAdapter{}
}

func (s *StubAdapter) MakeInvoice(ctx context.Context, connectorID string, params MakeInvoiceParams) (*MakeInvoiceResult, error) {
	return nil, errors.New("lightning backend not configured: make_invoice not available")
}

func (s *StubAdapter) LookupInvoice(ctx context.Context, connectorID string, params LookupInvoiceParams) (*LookupInvoiceResult, error) {
	return nil, errors.New("lightning backend not configured: lookup_invoice not available")
}
