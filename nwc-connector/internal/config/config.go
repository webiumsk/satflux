package config

import (
	"os"
	"strconv"
)

type Config struct {
	HTTPAddr       string
	DBURL          string
	RelayURL       string
	NWCMasterKey   string // AES key for encrypting connector secrets in DB
	PanelAPIKey    string // Optional: validate requests from Panel
}

func Load() *Config {
	return &Config{
		HTTPAddr:      getEnv("NWC_HTTP_ADDR", ":8082"),
		DBURL:         getEnv("DATABASE_URL", "postgres://d21panel:d21panel@postgres:5432/d21panel?sslmode=disable"),
		RelayURL:      getEnv("NWC_RELAY_URL", "wss://relay.getalby.com/v1"),
		NWCMasterKey:  os.Getenv("NWC_MASTER_KEY"),
		PanelAPIKey:   os.Getenv("NWC_PANEL_API_KEY"),
	}
}

func getEnv(key, defaultVal string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return defaultVal
}

func getEnvInt(key string, defaultVal int) int {
	if v := os.Getenv(key); v != "" {
		if i, err := strconv.Atoi(v); err == nil {
			return i
		}
	}
	return defaultVal
}
