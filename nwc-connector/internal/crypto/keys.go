package crypto

import (
	"crypto/aes"
	"crypto/cipher"
	"crypto/rand"
	"encoding/hex"
	"errors"
	"io"

	"github.com/nbd-wtf/go-nostr"
)

// GenerateKeypair creates a new Nostr keypair. Returns hex pubkey, hex privkey.
func GenerateKeypair() (pubkey, privkey string, err error) {
	sk := nostr.GeneratePrivateKey()
	pk, err := nostr.GetPublicKey(sk)
	if err != nil {
		return "", "", err
	}
	return pk, sk, nil
}

// EncryptSecret encrypts the secret (hex privkey) for storage. Uses AES-256-GCM; key must be 32 bytes (hex or raw).
func EncryptSecret(plaintext, masterKeyHex string) (string, error) {
	key := []byte(masterKeyHex)
	if len(masterKeyHex) == 64 {
		var err error
		key, err = hex.DecodeString(masterKeyHex)
		if err != nil {
			return "", err
		}
	}
	if len(key) != 32 {
		return "", errors.New("master key must be 32 bytes or 64 hex chars")
	}
	block, err := aes.NewCipher(key)
	if err != nil {
		return "", err
	}
	gcm, err := cipher.NewGCM(block)
	if err != nil {
		return "", err
	}
	nonce := make([]byte, gcm.NonceSize())
	if _, err := io.ReadFull(rand.Reader, nonce); err != nil {
		return "", err
	}
	ciphertext := gcm.Seal(nonce, nonce, []byte(plaintext), nil)
	return hex.EncodeToString(ciphertext), nil
}

// DecryptSecret decrypts the stored secret.
func DecryptSecret(ciphertextHex, masterKeyHex string) (string, error) {
	key := []byte(masterKeyHex)
	if len(masterKeyHex) == 64 {
		var err error
		key, err = hex.DecodeString(masterKeyHex)
		if err != nil {
			return "", err
		}
	}
	if len(key) != 32 {
		return "", errors.New("master key must be 32 bytes or 64 hex chars")
	}
	ciphertext, err := hex.DecodeString(ciphertextHex)
	if err != nil {
		return "", err
	}
	block, err := aes.NewCipher(key)
	if err != nil {
		return "", err
	}
	gcm, err := cipher.NewGCM(block)
	if err != nil {
		return "", err
	}
	nonceSize := gcm.NonceSize()
	if len(ciphertext) < nonceSize {
		return "", errors.New("ciphertext too short")
	}
	nonce, ciphertext := ciphertext[:nonceSize], ciphertext[nonceSize:]
	plain, err := gcm.Open(nil, nonce, ciphertext, nil)
	if err != nil {
		return "", err
	}
	return string(plain), nil
}
