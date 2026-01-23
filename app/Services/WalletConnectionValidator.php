<?php

namespace App\Services;

class WalletConnectionValidator
{
    /**
     * Parse Blink connection string.
     * 
     * Format: type=blink;server=https://api.blink.sv/graphql;api-key=blink_xxx;wallet-id=xxx
     * 
     * @param string $connectionString Connection string to parse
     * @return array Parsed values with keys: 'type', 'server', 'api_key', 'wallet_id', 'errors'
     */
    public function parseBlinkConnectionString(string $connectionString): array
    {
        $connectionString = trim($connectionString);
        $result = [
            'type' => null,
            'server' => null,
            'api_key' => null,
            'wallet_id' => null,
            'errors' => [],
        ];

        // Check if it's a connection string format (contains semicolons)
        if (strpos($connectionString, ';') !== false) {
            // Parse key=value pairs
            $parts = explode(';', $connectionString);
            foreach ($parts as $part) {
                $part = trim($part);
                if (empty($part)) {
                    continue;
                }

                if (strpos($part, '=') === false) {
                    $result['errors'][] = "Invalid part format: {$part}";
                    continue;
                }

                [$key, $value] = explode('=', $part, 2);
                $key = trim(strtolower($key));
                $value = trim($value);

                switch ($key) {
                    case 'type':
                        $result['type'] = $value;
                        break;
                    case 'server':
                        $result['server'] = $value;
                        break;
                    case 'api-key':
                    case 'apikey':
                        $result['api_key'] = $value;
                        break;
                    case 'wallet-id':
                    case 'walletid':
                        $result['wallet_id'] = $value;
                        break;
                }
            }

            // Validate required fields
            if ($result['type'] !== 'blink') {
                $result['errors'][] = "Type must be 'blink'";
            }
            if (empty($result['server'])) {
                $result['errors'][] = "Server is required";
            } elseif (!filter_var($result['server'], FILTER_VALIDATE_URL)) {
                $result['errors'][] = "Server must be a valid URL";
            }
            if (empty($result['api_key'])) {
                $result['errors'][] = "API key is required";
            }
            if (empty($result['wallet_id'])) {
                $result['errors'][] = "Wallet ID is required";
            }
        } else {
            // Legacy format: just a URL or token (allow for backward compatibility)
            if (filter_var($connectionString, FILTER_VALIDATE_URL) !== false) {
                // Valid URL format
                $result['server'] = $connectionString;
            } else {
                $result['errors'][] = "Invalid connection string format. Expected: type=blink;server=...;api-key=...;wallet-id=...";
            }
        }

        return $result;
    }

    /**
     * Parse Boltz connection string.
     * 
     * Note: Boltz/Aqua typically uses watch-only descriptors, not connection strings.
     * This method is for future compatibility if Boltz uses connection strings.
     * 
     * @param string $connectionString Connection string to parse
     * @return array Parsed values with keys: 'type', 'server', 'macaroon', 'wallet_id', 'errors'
     */
    public function parseBoltzConnectionString(string $connectionString): array
    {
        // For now, Boltz uses descriptors, not connection strings
        // This is a placeholder for future compatibility
        return [
            'type' => null,
            'server' => null,
            'macaroon' => null,
            'wallet_id' => null,
            'errors' => ['Boltz connection strings are not yet supported. Use watch-only descriptor instead.'],
        ];
    }

    /**
     * Validate Blink connection string/token.
     *
     * @param string $token Connection string or token
     * @return bool True if valid
     */
    public function validateBlinkToken(string $token): bool
    {
        // Try parsing as connection string first
        $parsed = $this->parseBlinkConnectionString($token);
        if (empty($parsed['errors'])) {
            return true;
        }

        // Fallback: legacy format validation (URL or simple token)
        $token = trim($token);
        
        if (empty($token) || strlen($token) < 10) {
            return false;
        }

        // Check if it's a valid URL
        if (filter_var($token, FILTER_VALIDATE_URL) !== false) {
            return true;
        }

        // Allow other connection string patterns (e.g., token-based)
        // Should not contain whitespace in the middle
        if (preg_match('/\s/', $token)) {
            return false;
        }

        return true;
    }

    /**
     * Validate Aqua watch-only descriptor.
     *
     * @param string $descriptor Bitcoin Core output descriptor
     * @return bool True if valid
     */
    public function validateAquaDescriptor(string $descriptor): bool
    {
        $descriptor = trim($descriptor);

        if (empty($descriptor)) {
            return false;
        }

        // Must NOT contain "prv" anywhere (private keys)
        if (stripos($descriptor, 'prv') !== false) {
            return false;
        }

        // Should contain xpub, ypub, zpub, or similar extended public keys
        // But NOT xprv, yprv, zprv (private keys)
        if (preg_match('/(xprv|yprv|zprv)/i', $descriptor)) {
            return false;
        }

        // Must be a valid output descriptor format
        // Common formats: wpkh(), wsh(), tr(), pkh(), sh(), etc.
        // Also supports complex nested formats like ct(slip77(...),elsh(wpkh(...)))
        // Check if descriptor contains at least one valid descriptor function
        $validFunctions = [
            'wpkh', 'wsh', 'tr', 'pkh', 'sh', 'addr', 'raw',  // Basic functions
            'ct', 'elsh', 'slip77',  // Complex nested functions (for Boltz/Aqua)
        ];
        
        $hasValidFunction = false;
        $descriptorLower = strtolower($descriptor);
        
        foreach ($validFunctions as $func) {
            // Check if function appears in the descriptor (not just at start)
            // Pattern: function name followed by opening parenthesis
            if (preg_match('/\b' . preg_quote($func, '/') . '\s*\(/', $descriptorLower)) {
                $hasValidFunction = true;
                break;
            }
        }

        if (!$hasValidFunction) {
            return false;
        }

        // Basic structure validation: should have balanced parentheses
        // This is a simple check - full validation would require a parser
        $openParens = substr_count($descriptor, '(');
        $closeParens = substr_count($descriptor, ')');
        if ($openParens !== $closeParens || $openParens === 0) {
            return false;
        }

        // Should contain at least one xpub/ypub/zpub (extended public key)
        // This is required for watch-only descriptors
        if (!preg_match('/(xpub|ypub|zpub|tpub|upub|vpub)/i', $descriptor)) {
            return false;
        }

        return true;
    }

    /**
     * Validate wallet connection based on type.
     *
     * @param string $type Connection type ('blink' or 'aqua_descriptor')
     * @param string $value Secret value to validate
     * @return array ['valid' => bool, 'type' => string|null, 'errors' => array, 'error' => string|null]
     */
    public function validate(string $type, string $value): array
    {
        \Illuminate\Support\Facades\Log::info('WalletConnectionValidator::validate called', [
            'type' => $type,
            'value_length' => strlen($value),
            'value_preview' => substr($value, 0, 100) . '...',
        ]);
        
        $errors = [];
        $returnType = null;

        if ($type === 'blink') {
            $returnType = 'blink';
            \Illuminate\Support\Facades\Log::info('Validating Blink connection string', [
                'type' => $type,
            ]);
            
            // Validate Blink connection string format
            $parsed = $this->parseBlinkConnectionString($value);
            \Illuminate\Support\Facades\Log::info('Blink connection string parsed', [
                'type' => $type,
                'parsed_errors' => $parsed['errors'] ?? [],
                'parsed_type' => $parsed['type'] ?? 'NULL',
                'parsed_server' => $parsed['server'] ?? 'NULL',
            ]);
            
            if (!empty($parsed['errors'])) {
                $errors = array_merge($errors, $parsed['errors']);
            } elseif (!$this->validateBlinkToken($value)) {
                $errors[] = 'Invalid Blink connection string format. Expected: type=blink;server=https://...;api-key=...;wallet-id=...';
            }
        } elseif ($type === 'aqua_descriptor') {
            $returnType = 'aqua_descriptor';
            \Illuminate\Support\Facades\Log::info('Validating Aqua descriptor', [
                'type' => $type,
            ]);
            
            $isValid = $this->validateAquaDescriptor($value);
            \Illuminate\Support\Facades\Log::info('Aqua descriptor validation result', [
                'type' => $type,
                'is_valid' => $isValid,
            ]);
            
            if (!$isValid) {
                $errors[] = 'Invalid descriptor format. Must be a valid Bitcoin Core output descriptor (e.g., wpkh(), tr(), wsh(), or complex formats like ct(slip77(...),elsh(wpkh(...)))) and must not contain private keys (prv).';
            }
        } else {
            \Illuminate\Support\Facades\Log::error('Unsupported wallet connection type', [
                'type' => $type,
            ]);
            throw new \InvalidArgumentException("Unsupported wallet connection type: {$type}");
        }

        $result = [
            'valid' => empty($errors),
            'type' => $returnType,
            'errors' => $errors,
            'error' => !empty($errors) ? implode('; ', $errors) : null,
        ];
        
        \Illuminate\Support\Facades\Log::info('WalletConnectionValidator::validate result', [
            'type' => $type,
            'valid' => $result['valid'],
            'errors_count' => count($errors),
            'errors' => $errors,
        ]);
        
        return $result;
    }
}


