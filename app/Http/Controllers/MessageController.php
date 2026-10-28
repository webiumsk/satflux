<?php

namespace App\Http\Controllers;

use App\Models\UserMessage;
use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Services\BtcPay\NotificationService;
use App\Services\Messages\MessageFeedService;
use App\Services\WalletSecurity\WalletSecurityNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MessageController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService,
        protected MessageFeedService $feed
    ) {}

    /** Prefix that tells a Satflux-local message id apart from a BTCPay notification id. */
    public const LOCAL_ID_PREFIX = 'sf_';

    /** List the merged, filtered chronological notification feed. */
    public function index(Request $request): JsonResponse
    {
        $options = $request->validate([
            'category' => ['sometimes', 'string', 'in:all,critical,invoices,other'],
            'unread' => ['sometimes', 'in:0,1,true,false'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'cursor' => ['sometimes', 'string', 'max:4096'],
        ]);
        if (($options['page'] ?? 1) > 1 && ! isset($options['cursor'])) {
            throw ValidationException::withMessages(['cursor' => ['Use the next message cursor to continue.']]);
        }
        $options['unread'] = $request->boolean('unread');
        $options['per_page'] = (int) ($options['per_page'] ?? 20);

        return response()->json($this->feed->list($request->user(), $options));
    }

    /**
     * Get unread notification count from BTCPay.
     */
    public function count(Request $request): JsonResponse
    {
        $user = $request->user();
        $apiKey = $user->btcpay_api_key;
        $securityUnread = UserMessage::query()
            ->where('user_id', $user->id)
            ->whereIn('type', [WalletSecurityNotifier::TYPE, 'critical'])
            ->whereNull('read_at')
            ->count();

        $localUnread = UserMessage::query()->where('user_id', $user->id)->whereNull('read_at')->count();
        $localAvailable = UserMessage::query()->where('user_id', $user->id)
            ->where(fn ($query) => $query->whereNull('read_at')->orWhere('created_at', '>=', now()->subDays(30)))->exists();

        if (! $apiKey) {
            return response()->json([
                'data' => ['unread' => $localUnread, 'security_unread' => $securityUnread],
                'available' => $localAvailable,
            ]);
        }

        try {
            $unread = $this->notificationService->getUnreadCount($apiKey);

            return response()->json([
                'data' => ['unread' => $unread + $localUnread, 'security_unread' => $securityUnread],
                'available' => true,
            ]);
        } catch (BtcPayException $e) {
            if ($e->getStatusCode() === 403) {
                return response()->json([
                    'data' => ['unread' => $localUnread, 'security_unread' => $securityUnread],
                    'available' => $localAvailable,
                ]);
            }
            throw $e;
        }
    }

    /**
     * Mark a BTCPay notification as seen.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (str_starts_with($id, self::LOCAL_ID_PREFIX)) {
            $message = UserMessage::query()
                ->where('user_id', $user->id)
                ->whereKey((int) substr($id, strlen(self::LOCAL_ID_PREFIX)))
                ->firstOrFail();
            $message->markAsRead();

            return response()->json(['data' => $this->feed->mapLocal($message)]);
        }
        $apiKey = $user->btcpay_api_key;

        if (! $apiKey) {
            return response()->json(['message' => 'No BTCPay API key'], 400);
        }

        try {
            $updated = $this->notificationService->markAsSeen($apiKey, $id, true);

            return response()->json([
                'data' => $this->feed->mapRemote($updated, $user),
            ]);
        } catch (BtcPayException $e) {
            if ($e->getStatusCode() === 404) {
                abort(404);
            }
            throw $e;
        }
    }

    /**
     * Mark all BTCPay notifications as seen (batch).
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        UserMessage::query()->where('user_id', $user->id)->whereNull('read_at')->update(['read_at' => now()]);
        $apiKey = $user->btcpay_api_key;

        if (! $apiKey) {
            return response()->json(['data' => ['success' => true]]);
        }

        try {
            $result = $this->notificationService->listNotifications($apiKey, [
                'take' => 100,
                'skip' => 0,
                'seen' => false,
            ]);

            $unread = $result['data'];
            foreach ($unread as $n) {
                $nid = $n['id'] ?? null;
                if ($nid) {
                    try {
                        $this->notificationService->markAsSeen($apiKey, $nid, true);
                    } catch (\Throwable $e) {
                        // Continue with others
                    }
                }
            }

            return response()->json(['data' => ['success' => true]]);
        } catch (BtcPayException $e) {
            if ($e->getStatusCode() === 403) {
                return response()->json(['data' => ['success' => true]]);
            }
            throw $e;
        }
    }
}
