package api

import (
	"encoding/json"
	"net/http"
	"net/url"
	"strings"

	"github.com/google/uuid"
	"github.com/satflux/nwc-connector/internal/config"
	"github.com/satflux/nwc-connector/internal/crypto"
	"github.com/satflux/nwc-connector/internal/db"
	"github.com/satflux/nwc-connector/internal/nwcuri"
)

type Server struct {
	cfg *config.Config
	db  *db.DB
}

func New(cfg *config.Config, database *db.DB) *Server {
	return &Server{cfg: cfg, db: database}
}

// CreateConnectorRequest from Panel: store_id (UUID), btcpay_store_id (string), optional backend_nwc_uri (merchant wallet NWC URI)
type CreateConnectorRequest struct {
	StoreID        string `json:"store_id"`
	BtcpayStoreID  string `json:"btcpay_store_id"`
	BackendNwcURI  string `json:"backend_nwc_uri,omitempty"`
}

// CreateConnectorResponse returns connector_id and full connection string for BTCPay
type CreateConnectorResponse struct {
	ConnectorID      string `json:"connector_id"`
	ConnectionString string `json:"connection_string"` // type=nwc;key=nostr+walletconnect:...
	NWCURI           string `json:"nwc_uri"`           // nostr+walletconnect:pubkey?relay=...&secret=...
}

func (s *Server) CreateConnector(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
		return
	}
	var req CreateConnectorRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "invalid body", http.StatusBadRequest)
		return
	}
	storeID, err := uuid.Parse(req.StoreID)
	if err != nil || req.BtcpayStoreID == "" {
		http.Error(w, "store_id and btcpay_store_id required", http.StatusBadRequest)
		return
	}

	pubkey, privkey, err := crypto.GenerateKeypair()
	if err != nil {
		http.Error(w, "key generation failed", http.StatusInternalServerError)
		return
	}
	if s.cfg.NWCMasterKey == "" {
		http.Error(w, "NWC_MASTER_KEY not configured", http.StatusInternalServerError)
		return
	}
	encrypted, err := crypto.EncryptSecret(privkey, s.cfg.NWCMasterKey)
	if err != nil {
		http.Error(w, "encryption failed", http.StatusInternalServerError)
		return
	}

	relayURL := s.cfg.RelayURL
	backendType := "lnd"
	var backendConfigEncrypted *string
	if strings.TrimSpace(req.BackendNwcURI) != "" {
		parsed, parseErr := nwcuri.ParseNWCURI(req.BackendNwcURI)
		if parseErr != nil {
			http.Error(w, "invalid backend_nwc_uri: "+parseErr.Error(), http.StatusBadRequest)
			return
		}
		configJSON, _ := json.Marshal(parsed)
		encBackend, encErr := crypto.EncryptSecret(string(configJSON), s.cfg.NWCMasterKey)
		if encErr != nil {
			http.Error(w, "encryption failed", http.StatusInternalServerError)
			return
		}
		backendType = "nwc"
		backendConfigEncrypted = &encBackend
	}
	connectorID, err := s.db.CreateConnector(r.Context(), storeID, req.BtcpayStoreID, pubkey, encrypted, relayURL, backendType, backendConfigEncrypted)
	if err != nil {
		http.Error(w, "db create failed", http.StatusInternalServerError)
		return
	}

	// BTCPay Nostr plugin format: type=nwc;key=nostr+walletconnect:<pubkey>?relay=wss%3A%2F%2F<host>&secret=<secret>
	nwcURI := "nostr+walletconnect:" + pubkey + "?relay=" + url.QueryEscape(relayURL) + "&secret=" + privkey
	connectionString := "type=nwc;key=" + nwcURI

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(CreateConnectorResponse{
		ConnectorID:      connectorID.String(),
		ConnectionString: connectionString,
		NWCURI:           nwcURI,
	})
}

func (s *Server) GetConnector(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
		return
	}
	idStr := r.PathValue("id")
	if idStr == "" {
		http.Error(w, "id required", http.StatusBadRequest)
		return
	}
	id, err := uuid.Parse(idStr)
	if err != nil {
		http.Error(w, "invalid id", http.StatusBadRequest)
		return
	}
	conn, err := s.db.GetConnectorByID(r.Context(), id)
	if err != nil || conn == nil {
		http.Error(w, "not found", http.StatusNotFound)
		return
	}
	// Return masked pubkey for display (first 6 + ... + last 6)
	masked := maskPubkey(conn.NostrPubkey)
	resp := map[string]any{
		"connector_id":         conn.ID.String(),
		"store_id":             conn.StoreID.String(),
		"btcpay_store_id":      conn.BtcpayStoreID,
		"nostr_pubkey_masked":  masked,
		"relay_url":            conn.RelayURL,
		"status":               conn.Status,
		"last_seen_at":         conn.LastSeenAt,
	}
	// Include connection_string so Panel can show it for copying into BTCPay
	if s.cfg.NWCMasterKey != "" {
		privkey, decErr := crypto.DecryptSecret(conn.NostrSecretEncrypted, s.cfg.NWCMasterKey)
		if decErr == nil {
			nwcURI := "nostr+walletconnect:" + conn.NostrPubkey + "?relay=" + url.QueryEscape(conn.RelayURL) + "&secret=" + privkey
			resp["connection_string"] = "type=nwc;key=" + nwcURI
		}
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(resp)
}

func (s *Server) RevokeConnector(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
		return
	}
	idStr := r.PathValue("id")
	if idStr == "" {
		http.Error(w, "id required", http.StatusBadRequest)
		return
	}
	id, err := uuid.Parse(idStr)
	if err != nil {
		http.Error(w, "invalid id", http.StatusBadRequest)
		return
	}
	if err := s.db.RevokeConnector(r.Context(), id); err != nil {
		http.Error(w, "revoke failed", http.StatusInternalServerError)
		return
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"status": "revoked"})
}

func maskPubkey(pk string) string {
	if len(pk) <= 12 {
		return "****"
	}
	return pk[:6] + "..." + pk[len(pk)-6:]
}

func (s *Server) Health(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
		return
	}
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{"status": "ok"})
}
