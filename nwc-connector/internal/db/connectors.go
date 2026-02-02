package db

import (
	"context"
	"encoding/json"
	"github.com/google/uuid"
)

type Connector struct {
	ID                   uuid.UUID
	StoreID              uuid.UUID
	BtcpayStoreID       string
	NostrPubkey         string
	NostrSecretEncrypted string
	RelayURL             string
	BackendType          string
	BackendConfigEncrypted *string
	AllowedMethods       []byte
	RateLimitPerMin      int
	Status               string
	LastSeenAt           *string
	CreatedAt            string
	UpdatedAt            string
}

func (db *DB) CreateConnector(ctx context.Context, storeID uuid.UUID, btcpayStoreID, pubkey, secretEncrypted, relayURL, backendType string, backendConfigEncrypted *string) (uuid.UUID, error) {
	id := uuid.New()
	allowed, _ := json.Marshal([]string{"make_invoice", "lookup_invoice"})
	_, err := db.Pool.Exec(ctx, `
		INSERT INTO nwc_connectors (id, store_id, btcpay_store_id, nostr_pubkey, nostr_secret_encrypted, relay_url, backend_type, backend_config_encrypted, allowed_methods, rate_limit_per_min, status)
		VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, 60, 'active')
	`, id, storeID, btcpayStoreID, pubkey, secretEncrypted, relayURL, backendType, backendConfigEncrypted, allowed)
	return id, err
}

func (db *DB) GetConnectorByID(ctx context.Context, id uuid.UUID) (*Connector, error) {
	var c Connector
	var storeIDStr string
	err := db.Pool.QueryRow(ctx, `
		SELECT id, store_id, btcpay_store_id, nostr_pubkey, nostr_secret_encrypted, relay_url, backend_type, backend_config_encrypted, allowed_methods, rate_limit_per_min, status, last_seen_at, created_at, updated_at
		FROM nwc_connectors WHERE id = $1 AND status = 'active'
	`, id).Scan(&c.ID, &storeIDStr, &c.BtcpayStoreID, &c.NostrPubkey, &c.NostrSecretEncrypted, &c.RelayURL, &c.BackendType, &c.BackendConfigEncrypted, &c.AllowedMethods, &c.RateLimitPerMin, &c.Status, &c.LastSeenAt, &c.CreatedAt, &c.UpdatedAt)
	if err != nil {
		return nil, err
	}
	c.StoreID, _ = uuid.Parse(storeIDStr)
	return &c, nil
}

func (db *DB) GetConnectorByStoreID(ctx context.Context, storeID uuid.UUID) (*Connector, error) {
	var c Connector
	var storeIDStr string
	err := db.Pool.QueryRow(ctx, `
		SELECT id, store_id, btcpay_store_id, nostr_pubkey, nostr_secret_encrypted, relay_url, backend_type, backend_config_encrypted, allowed_methods, rate_limit_per_min, status, last_seen_at, created_at, updated_at
		FROM nwc_connectors WHERE store_id = $1 AND status = 'active'
	`, storeID).Scan(&c.ID, &storeIDStr, &c.BtcpayStoreID, &c.NostrPubkey, &c.NostrSecretEncrypted, &c.RelayURL, &c.BackendType, &c.BackendConfigEncrypted, &c.AllowedMethods, &c.RateLimitPerMin, &c.Status, &c.LastSeenAt, &c.CreatedAt, &c.UpdatedAt)
	if err != nil {
		return nil, err
	}
	c.StoreID, _ = uuid.Parse(storeIDStr)
	return &c, nil
}

func (db *DB) RevokeConnector(ctx context.Context, id uuid.UUID) error {
	_, err := db.Pool.Exec(ctx, `UPDATE nwc_connectors SET status = 'revoked' WHERE id = $1`, id)
	return err
}

func (db *DB) ListActivePubkeys(ctx context.Context) ([]string, error) {
	rows, err := db.Pool.Query(ctx, `SELECT nostr_pubkey FROM nwc_connectors WHERE status = 'active'`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var pubkeys []string
	for rows.Next() {
		var p string
		if err := rows.Scan(&p); err != nil {
			return nil, err
		}
		pubkeys = append(pubkeys, p)
	}
	return pubkeys, rows.Err()
}

// ListActiveConnectors returns all active connectors (for publishing NIP-47 info events).
func (db *DB) ListActiveConnectors(ctx context.Context) ([]Connector, error) {
	rows, err := db.Pool.Query(ctx, `
		SELECT id, store_id, btcpay_store_id, nostr_pubkey, nostr_secret_encrypted, relay_url, backend_type, backend_config_encrypted, allowed_methods, rate_limit_per_min, status, last_seen_at, created_at, updated_at
		FROM nwc_connectors WHERE status = 'active'
	`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var list []Connector
	for rows.Next() {
		var c Connector
		var storeIDStr string
		if err := rows.Scan(&c.ID, &storeIDStr, &c.BtcpayStoreID, &c.NostrPubkey, &c.NostrSecretEncrypted, &c.RelayURL, &c.BackendType, &c.BackendConfigEncrypted, &c.AllowedMethods, &c.RateLimitPerMin, &c.Status, &c.LastSeenAt, &c.CreatedAt, &c.UpdatedAt); err != nil {
			return nil, err
		}
		c.StoreID, _ = uuid.Parse(storeIDStr)
		list = append(list, c)
	}
	return list, rows.Err()
}

func (db *DB) GetConnectorByPubkey(ctx context.Context, pubkey string) (*Connector, error) {
	var c Connector
	var storeIDStr string
	err := db.Pool.QueryRow(ctx, `
		SELECT id, store_id, btcpay_store_id, nostr_pubkey, nostr_secret_encrypted, relay_url, backend_type, backend_config_encrypted, allowed_methods, rate_limit_per_min, status, last_seen_at, created_at, updated_at
		FROM nwc_connectors WHERE nostr_pubkey = $1 AND status = 'active'
	`, pubkey).Scan(&c.ID, &storeIDStr, &c.BtcpayStoreID, &c.NostrPubkey, &c.NostrSecretEncrypted, &c.RelayURL, &c.BackendType, &c.BackendConfigEncrypted, &c.AllowedMethods, &c.RateLimitPerMin, &c.Status, &c.LastSeenAt, &c.CreatedAt, &c.UpdatedAt)
	if err != nil {
		return nil, err
	}
	c.StoreID, _ = uuid.Parse(storeIDStr)
	return &c, nil
}
