<?php

namespace App\Services\BtcPay;

use App\Services\BtcPay\Exceptions\BtcPayException;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function __construct(
        protected BtcPayClient $client
    ) {}

    /**
     * List notifications for a user using their BTCPay API key.
     *
     * @param  string  $userApiKey  User's BTCPay API key (with notification permissions)
     * @param  array  $options  skip, take, seen (bool), storeId (array)
     * @return array{data: array, total?: int} Notifications and optional total
     */
    public function listNotifications(string $userApiKey, array $options = []): array
    {
        $originalKey = $this->client->getApiKey();
        $this->client->setApiKey($userApiKey);

        try {
            $query = [];
            if (isset($options['skip'])) {
                $query['skip'] = (int) $options['skip'];
            }
            if (isset($options['take'])) {
                $query['take'] = (int) $options['take'];
            }
            if (isset($options['seen']) && $options['seen'] !== null) {
                $query['seen'] = $options['seen'] ? 'true' : 'false';
            }
            if (! empty($options['storeId'])) {
                $query['storeId'] = (array) $options['storeId'];
            }

            $result = $this->client->get('/api/v1/users/me/notifications', $query);

            $data = $result['data'] ?? $result;
            if (! is_array($data)) {
                $data = [];
            }

            return ['data' => $data];
        } catch (BtcPayException $e) {
            Log::warning('BTCPay notifications fetch failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            throw $e;
        } finally {
            $this->client->setApiKey($originalKey);
        }
    }

    /**
     * Mark a notification as seen.
     */
    public function markAsSeen(string $userApiKey, string $notificationId, bool $seen = true): array
    {
        $originalKey = $this->client->getApiKey();
        $this->client->setApiKey($userApiKey);

        try {
            return $this->client->put("/api/v1/users/me/notifications/{$notificationId}", [
                'seen' => $seen,
            ]);
        } finally {
            $this->client->setApiKey($originalKey);
        }
    }

    /**
     * Get unread notification count.
     * BTCPay API doesn't return total, so we fetch unread with a limit and count.
     */
    public function getUnreadCount(string $userApiKey): int
    {
        $result = $this->listNotifications($userApiKey, [
            'take' => 200,
            'skip' => 0,
            'seen' => false,
        ]);
        $unread = $result['data'] ?? [];

        return is_array($unread) ? count($unread) : 0;
    }
}
