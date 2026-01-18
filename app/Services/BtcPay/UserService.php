<?php

namespace App\Services\BtcPay;

use Illuminate\Support\Facades\Log;

class UserService
{
    protected BtcPayClient $client;

    public function __construct(BtcPayClient $client)
    {
        $this->client = $client;
    }

    /**
     * Create a new user in BTCPay Server.
     * 
     * When password is provided, user is created as active (not "Pending Invitation").
     * 
     * @param array $data User data containing:
     *   - email (required): User email address
     *   - password (optional): User password - if provided, user is created as active
     *   - isAdministrator (optional): Whether user should be administrator (default: false)
     * @return array Created user data
     * @throws \App\Services\BtcPay\Exceptions\BtcPayException
     */
    public function createUser(array $data): array
    {
        try {
            // Prepare user data for BTCPay API
            $userData = [
                'email' => $data['email'],
                'isAdministrator' => $data['isAdministrator'] ?? false,
            ];

            // Add password if provided - this ensures user is created as active
            if (isset($data['password']) && !empty($data['password'])) {
                $userData['password'] = $data['password'];
            }

            return $this->client->post('/api/v1/users', $userData);
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            Log::error('BTCPay user creation failed', [
                'error' => $e->getMessage(),
                'data' => [
                    'email' => $data['email'] ?? 'N/A',
                    'has_password' => isset($data['password']) && !empty($data['password']),
                ],
            ]);
            throw $e;
        }
    }

    /**
     * Get a user by ID from BTCPay Server.
     * 
     * @param string $userId BTCPay user ID
     * @return array User data
     * @throws \App\Services\BtcPay\Exceptions\BtcPayException
     */
    public function getUser(string $userId): array
    {
        try {
            return $this->client->get("/api/v1/users/{$userId}");
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            Log::error('BTCPay user retrieval failed', [
                'error' => $e->getMessage(),
                'userId' => $userId,
            ]);
            throw $e;
        }
    }

    /**
     * List all users from BTCPay Server.
     * 
     * @return array List of users
     * @throws \App\Services\BtcPay\Exceptions\BtcPayException
     */
    public function listUsers(): array
    {
        try {
            return $this->client->get('/api/v1/users');
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            Log::error('BTCPay users listing failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Check if email exists on BTCPay Server.
     * 
     * @param string $email Email address to check
     * @return bool True if email exists, false otherwise
     * @throws \App\Services\BtcPay\Exceptions\BtcPayException
     */
    public function checkEmailExists(string $email): bool
    {
        try {
            $users = $this->listUsers();

            // Check if any user has this email
            foreach ($users as $user) {
                if (isset($user['email']) && strtolower($user['email']) === strtolower($email)) {
                    return true;
                }
            }

            return false;
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            Log::error('BTCPay email check failed', [
                'error' => $e->getMessage(),
                'email' => $email,
            ]);
            throw $e;
        }
    }

    /**
     * Get user by email from BTCPay Server.
     * 
     * @param string $email Email address
     * @return array|null User data or null if not found
     * @throws \App\Services\BtcPay\Exceptions\BtcPayException
     */
    public function getUserByEmail(string $email): ?array
    {
        try {
            $users = $this->listUsers();

            // Find user with matching email
            foreach ($users as $user) {
                if (isset($user['email']) && strtolower($user['email']) === strtolower($email)) {
                    return $user;
                }
            }

            return null;
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            Log::error('BTCPay user retrieval by email failed', [
                'error' => $e->getMessage(),
                'email' => $email,
            ]);
            throw $e;
        }
    }

    /**
     * Create an API key for a user in BTCPay Server.
     * Requires server-level API key with user management permissions.
     * 
     * @param string $userId BTCPay user ID
     * @param array $permissions Permissions for the API key (e.g., ['btcpay.store.cancreateinvoice', 'btcpay.store.canviewstoresettings'])
     * @param array $specificStores Optional: array of store IDs to limit API key access to specific stores
     * @param string $label Optional: label for the API key
     * @return array Created API key data (contains 'apiKey' field with the actual key)
     * @throws \App\Services\BtcPay\Exceptions\BtcPayException
     */
    public function createApiKey(string $userId, array $permissions = [], array $specificStores = [], string $label = 'UZOL21 API Key'): array
    {
        try {
            // Default permissions if none provided
            // Note: Some permissions may not be available in all BTCPay versions
            // 'btcpay.store.cancreatestores' is not a valid permission string
            // 'btcpay.store.canmanagepaymentrequests' may not be available in some versions
            // Store creation is handled via user permissions/roles, not API key permissions
            $defaultPermissions = [
                'btcpay.store.cancreateinvoice',
                'btcpay.store.canviewstoresettings',
                'btcpay.store.canmodifyinvoices',
                'btcpay.store.canmodifystoresettings',
                'btcpay.store.canviewinvoices',
            ];

            // Use provided permissions if not empty, otherwise use defaults
            $finalPermissions = !empty($permissions) ? $permissions : $defaultPermissions;

            $data = [
                'label' => $label,
                'permissions' => $finalPermissions,
            ];

            if (!empty($specificStores)) {
                $data['strict'] = true;
                $data['storeMode'] = 'Specific';
                $data['specificStores'] = $specificStores;
            }

            return $this->client->post("/api/v1/users/{$userId}/api-keys", $data);
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            Log::error('BTCPay API key creation failed', [
                'error' => $e->getMessage(),
                'userId' => $userId,
                'permissions_provided' => $permissions,
                'permissions_used' => $finalPermissions,
                'label' => $label,
            ]);
            throw $e;
        }
    }

    /**
     * List API keys for a user in BTCPay Server.
     * 
     * @param string $userId BTCPay user ID
     * @return array List of API keys
     * @throws \App\Services\BtcPay\Exceptions\BtcPayException
     */
    public function listApiKeys(string $userId): array
    {
        try {
            return $this->client->get("/api/v1/users/{$userId}/api-keys");
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            Log::error('BTCPay API keys listing failed', [
                'error' => $e->getMessage(),
                'userId' => $userId,
            ]);
            throw $e;
        }
    }

    /**
     * Update a user in BTCPay Server.
     * 
     * @param string $userId BTCPay user ID
     * @param array $data User data to update
     * @return array Updated user data
     * @throws \App\Services\BtcPay\Exceptions\BtcPayException
     */
    public function updateUser(string $userId, array $data): array
    {
        try {
            // Try PATCH first (more RESTful for partial updates)
            // If that fails, try PUT
            try {
                return $this->client->patch("/api/v1/users/{$userId}", $data);
            } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
                if (str_contains($e->getMessage(), '405') || str_contains($e->getMessage(), 'Method Not Allowed')) {
                    // PATCH not supported, try PUT
                    return $this->client->put("/api/v1/users/{$userId}", $data);
                }
                throw $e;
            }
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            Log::error('BTCPay user update failed', [
                'error' => $e->getMessage(),
                'userId' => $userId,
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Confirm email for a user in BTCPay Server.
     * Tries various possible endpoints for email confirmation.
     * 
     * @param string $userId BTCPay user ID
     * @return array Updated user data
     * @throws \App\Services\BtcPay\Exceptions\BtcPayException
     */
    public function confirmUserEmail(string $userId): array
    {
        // Try different possible endpoints for email confirmation
        $possibleEndpoints = [
            "/api/v1/users/{$userId}/confirm-email",
            "/api/v1/users/{$userId}/email/confirm",
            "/api/v1/users/{$userId}/verify-email",
        ];

        foreach ($possibleEndpoints as $endpoint) {
            try {
                return $this->client->post($endpoint);
            } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
                // Continue to next endpoint if this one doesn't exist
                if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'Not Found')) {
                    continue;
                }
                // If it's a different error (e.g., 401, 403), throw it
                throw $e;
            }
        }

        // If all endpoints failed, throw the last exception
        throw new \App\Services\BtcPay\Exceptions\BtcPayException('No email confirmation endpoint found');
    }

    /**
     * Accept a BTCPay user invitation by calling the invitation URL.
     * 
     * Invitation URLs have format: https://pay.example.org/invite/{inviteId}/{approvalCode}
     * 
     * @param string $invitationUrl Full invitation URL from BTCPay API response
     * @return bool True if invitation was accepted successfully, false otherwise
     */
    public function acceptInvitation(string $invitationUrl): bool
    {
        try {
            // Parse invitation URL to extract invite ID and approval code
            // Format: https://pay.example.org/invite/{inviteId}/{approvalCode}
            if (!preg_match('#/invite/([^/]+)/([^/?]+)#', $invitationUrl, $matches)) {
                Log::warning('Invalid invitation URL format', [
                    'invitation_url' => $invitationUrl,
                ]);
                return false;
            }

            $inviteId = $matches[1];
            $approvalCode = $matches[2];

            // Try API endpoint first (if available)
            $possibleApiEndpoints = [
                "/api/v1/users/invitations/{$inviteId}/accept",
                "/api/v1/invitations/{$inviteId}/accept",
            ];

            foreach ($possibleApiEndpoints as $endpoint) {
                try {
                    $this->client->post($endpoint, ['approvalCode' => $approvalCode]);
                    Log::info('BTCPay invitation accepted via API', [
                        'invite_id' => $inviteId,
                        'endpoint' => $endpoint,
                    ]);
                    return true;
                } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
                    // Continue to next endpoint if this one doesn't exist
                    if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'Not Found')) {
                        continue;
                    }
                    // If it's a different error, log and try direct URL call
                    Log::warning('BTCPay invitation API endpoint failed', [
                        'invite_id' => $inviteId,
                        'endpoint' => $endpoint,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // If API endpoints don't work, make a GET request to the invitation URL
            // This simulates clicking the invitation link in a browser
            $baseUrl = rtrim(config('services.btcpay.base_url', env('BTCPAY_BASE_URL')), '/');
            $invitationPath = parse_url($invitationUrl, PHP_URL_PATH);

            if ($invitationPath) {
                // Use the same base URL as BTCPay API to maintain session/cookies if needed
                $response = \Illuminate\Support\Facades\Http::baseUrl($baseUrl)
                    ->withHeaders([
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                        'User-Agent' => 'BTCPay-UZOL21/1.0',
                    ])
                    ->timeout(30)
                    ->get($invitationPath);

                if ($response->successful()) {
                    Log::info('BTCPay invitation accepted via direct URL call', [
                        'invite_id' => $inviteId,
                        'url' => $invitationUrl,
                        'status' => $response->status(),
                    ]);
                    return true;
                } else {
                    Log::warning('BTCPay invitation URL call failed', [
                        'invite_id' => $inviteId,
                        'url' => $invitationUrl,
                        'status' => $response->status(),
                    ]);
                    return false;
                }
            }

            return false;
        } catch (\Exception $e) {
            Log::error('BTCPay invitation acceptance failed', [
                'invitation_url' => $invitationUrl,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get the BTCPay user ID associated with the server-level API key (admin user).
     * This is the admin account that the server-level API key belongs to.
     * 
     * @return string|null BTCPay user ID or null if not found
     * @throws \App\Services\BtcPay\Exceptions\BtcPayException
     */
    public function getAdminBtcPayUserId(): ?string
    {
        try {
            // Cache the result since it doesn't change frequently
            return \Illuminate\Support\Facades\Cache::remember('btcpay:admin_user_id', 3600, function () {
                // Try different possible endpoints to get current user info
                $possibleEndpoints = [
                    '/api/v1/api-keys/current',
                    '/api/v1/users/me',
                    '/api/v1/me',
                ];

                foreach ($possibleEndpoints as $endpoint) {
                    try {
                        $response = $this->client->get($endpoint);
                        
                        // Extract user ID from response
                        $userId = $response['id'] ?? $response['userId'] ?? $response['user']['id'] ?? null;
                        
                        if ($userId) {
                            \Illuminate\Support\Facades\Log::info('Retrieved admin BTCPay user ID', [
                                'user_id' => $userId,
                                'endpoint' => $endpoint,
                            ]);
                            return $userId;
                        }
                    } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
                        // Continue to next endpoint if this one doesn't exist
                        if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'Not Found')) {
                            continue;
                        }
                        // If it's a different error, log and try next
                        \Illuminate\Support\Facades\Log::warning('Failed to get admin user ID from endpoint', [
                            'endpoint' => $endpoint,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Fallback: list users and find admin user
                try {
                    $users = $this->listUsers();
                    foreach ($users as $user) {
                        if (isset($user['isAdministrator']) && $user['isAdministrator'] === true) {
                            $adminId = $user['id'] ?? $user['userId'] ?? null;
                            if ($adminId) {
                                \Illuminate\Support\Facades\Log::info('Found admin BTCPay user ID via user list', [
                                    'user_id' => $adminId,
                                ]);
                                return $adminId;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to find admin user via user list', [
                        'error' => $e->getMessage(),
                    ]);
                }

                \Illuminate\Support\Facades\Log::error('Could not determine admin BTCPay user ID');
                return null;
            });
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            \Illuminate\Support\Facades\Log::error('Failed to get admin BTCPay user ID', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
