<?php

namespace App\Services\Messages;

use App\Http\Controllers\MessageController;
use App\Models\Store;
use App\Models\User;
use App\Models\UserMessage;
use App\Services\BtcPay\Exceptions\BtcPayException;
use App\Services\BtcPay\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class MessageFeedService
{
    private const BATCH_SIZE = 100;

    private const MAX_BATCHES = 5;

    public function __construct(private NotificationService $notifications) {}

    /**
     * Merge a local keyset and the upstream newest-first stream without dropping
     * older matches when a sparse filter exhausts this request's scan budget.
     * BTCPay only offers offsets: newly inserted upstream rows require a refresh.
     *
     * @param  array{category?: string, unread?: bool, per_page?: int, cursor?: string}  $options
     * @return array<string, mixed>
     */
    public function list(User $user, array $options): array
    {
        $category = $options['category'] ?? 'all';
        $unread = $options['unread'] ?? false;
        $perPage = $options['per_page'] ?? 20;
        $state = $this->decodeCursor($options['cursor'] ?? null, $user, $category, $unread, $perPage);
        $localQuery = $this->localQuery($user, $category, $unread);
        $localAvailable = $this->localQuery($user, 'all', false)->exists();
        if ($state['local_before'] !== null) {
            $before = $state['local_before'];
            $localQuery->where(function (Builder $query) use ($before) {
                $query->where('created_at', '<', $before['time'])
                    ->orWhere(fn (Builder $query) => $query->where('created_at', $before['time'])->where('id', '<', $before['id']));
            });
        }
        $local = $localQuery->orderByDesc('created_at')->orderByDesc('id')->limit($perPage + 1)->get();
        $localIndex = 0;
        $remote = [];
        $remoteIndex = 0;
        $batches = 0;
        $remoteDone = ! $user->btcpay_api_key || $category === 'critical';
        $remoteAvailable = (bool) $user->btcpay_api_key;
        $scanLimited = false;
        $frontier = null;
        $stores = null;

        // Keep an unconsumed matching upstream item as a peek until the local
        // stream catches up; the cursor advances only for rows actually consumed.
        $peek = function () use (&$remote, &$remoteIndex, &$remoteDone, &$remoteAvailable, &$batches, &$scanLimited, &$frontier, &$state, $user, $category, $unread): ?array {
            while (true) {
                if ($remoteIndex >= count($remote)) {
                    if ($remoteDone) {
                        return null;
                    }
                    if ($batches >= self::MAX_BATCHES) {
                        $scanLimited = true;

                        return null;
                    }
                    try {
                        $result = $this->notifications->listNotifications($user->btcpay_api_key, [
                            'skip' => $state['remote_skip'], 'take' => self::BATCH_SIZE,
                        ]);
                    } catch (BtcPayException $exception) {
                        if ($exception->getStatusCode() !== 403) {
                            throw $exception;
                        }
                        $remoteDone = true;
                        $remoteAvailable = false;

                        return null;
                    }
                    $remote = array_values($result['data']);
                    $remoteIndex = 0;
                    $batches++;
                    $remoteDone = count($remote) < self::BATCH_SIZE;
                    if ($remote === []) {
                        return null;
                    }
                }
                $row = $remote[$remoteIndex];
                $rowCategory = $this->remoteCategory($row);
                if (($category === 'all' || $category === $rowCategory) && (! $unread || ! ($row['seen'] ?? false))) {
                    return $row;
                }
                $frontier = (int) ($row['createdTime'] ?? 0);
                $remoteIndex++;
                $state['remote_skip']++;
            }
        };

        $items = [];
        while (count($items) < $perPage) {
            $nextRemote = $peek();
            $nextLocal = $local->get($localIndex);
            if ($nextLocal && ($nextRemote === null || $nextLocal->created_at->getTimestamp() >= (int) ($nextRemote['createdTime'] ?? 0))) {
                // A scan limit is not the end of remote history. Never emit a
                // local row older than the unscanned upstream frontier.
                if ($scanLimited && $frontier !== null && $nextLocal->created_at->getTimestamp() < $frontier) {
                    break;
                }
                $items[] = $this->mapLocal($nextLocal);
                $state['local_before'] = ['time' => $nextLocal->getRawOriginal('created_at'), 'id' => $nextLocal->id];
                $localIndex++;
            } elseif ($nextRemote !== null) {
                $stores ??= Store::query()->where('user_id', $user->id)->pluck('id', 'btcpay_store_id')->all();
                $items[] = $this->mapRemote($nextRemote, $user, $stores);
                $frontier = (int) ($nextRemote['createdTime'] ?? 0);
                $remoteIndex++;
                $state['remote_skip']++;
            } else {
                break;
            }
        }

        // Consume trailing nonmatches before advertising another filtered page.
        // A scan budget exhaustion still needs a continuation, even without a peek.
        $pendingRemote = $peek();
        $hasMore = $localIndex < $local->count() || $pendingRemote !== null || $scanLimited;
        $page = $state['page'];
        $state['page']++;
        $state['emitted'] += count($items);

        return [
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'last_page' => $hasMore ? $page + 1 : $page,
                'per_page' => $perPage,
                'total' => $state['emitted'] + ($hasMore ? 1 : 0),
                'total_is_exact' => ! $hasMore,
                'has_more' => $hasMore,
                'next_cursor' => $hasMore ? Crypt::encryptString(json_encode($state, JSON_THROW_ON_ERROR)) : null,
                'scan_limited' => $scanLimited,
            ],
            'available' => $remoteAvailable || $localAvailable,
        ];
    }

    /** @return Builder<UserMessage> */
    private function localQuery(User $user, string $category, bool $unread): Builder
    {
        $query = UserMessage::query()->where('user_id', $user->id)
            ->where(fn (Builder $query) => $query->whereNull('read_at')->orWhere('created_at', '>=', now()->subDays(30)));
        if ($unread) {
            $query->whereNull('read_at');
        }
        if ($category === 'critical') {
            $query->whereIn('type', ['security', 'critical']);
        } elseif ($category === 'invoices') {
            $query->whereRaw('LOWER(type) LIKE ?', ['invoice%']);
        } elseif ($category === 'other') {
            $query->whereNotIn('type', ['security', 'critical'])->whereRaw('LOWER(type) NOT LIKE ?', ['invoice%']);
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private function decodeCursor(?string $cursor, User $user, string $category, bool $unread, int $perPage): array
    {
        if ($cursor === null) {
            return ['version' => 1, 'user' => $user->id, 'category' => $category, 'unread' => $unread, 'per_page' => $perPage,
                'expires' => now()->addHour()->getTimestamp(), 'local_before' => null, 'remote_skip' => 0, 'page' => 1, 'emitted' => 0];
        }
        try {
            $state = json_decode(Crypt::decryptString($cursor), true, 16, JSON_THROW_ON_ERROR);
            if (! is_array($state) || ($state['version'] ?? null) !== 1 || ($state['user'] ?? null) !== $user->id
                || ($state['category'] ?? null) !== $category || ($state['unread'] ?? null) !== $unread
                || ($state['per_page'] ?? null) !== $perPage || ($state['expires'] ?? 0) <= now()->getTimestamp()) {
                throw new \UnexpectedValueException;
            }

            return $state;
        } catch (\Throwable) {
            throw ValidationException::withMessages(['cursor' => ['This message cursor is invalid or expired. Refresh the messages.']]);
        }
    }

    /** @return array<string, mixed> */
    public function mapLocal(UserMessage $message): array
    {
        return [
            'id' => MessageController::LOCAL_ID_PREFIX.$message->id,
            'type' => $message->type,
            'category' => in_array($message->type, ['security', 'critical'], true) ? 'critical' : (str_starts_with(strtolower($message->type), 'invoice') ? 'invoices' : 'other'),
            'title' => $message->title,
            'body' => $message->body,
            'link' => $message->link,
            'link_text' => $message->link_text,
            'read_at' => $message->read_at?->toIso8601String(),
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $notification */
    private function remoteCategory(array $notification): string
    {
        return str_starts_with(strtolower($notification['type'] ?? ''), 'invoice') ? 'invoices' : 'other';
    }

    /**
     * @param  array<string, mixed>  $notification
     * @param  array<string, string>|null  $stores  Owned local UUIDs indexed by upstream ID; never serialized.
     * @return array<string, mixed>
     */
    public function mapRemote(array $notification, User $user, ?array $stores = null): array
    {
        $stores ??= Store::query()->where('user_id', $user->id)->pluck('id', 'btcpay_store_id')->all();
        $type = $notification['type'] ?? 'info';
        $body = $notification['body'] ?? '';
        $created = gmdate('Y-m-d\TH:i:s\Z', (int) ($notification['createdTime'] ?? 0));
        $firstLine = trim(explode("\n", $body)[0]);

        return [
            'id' => $notification['id'] ?? '',
            'type' => $type,
            'category' => $this->remoteCategory($notification),
            'title' => $firstLine !== '' ? mb_strimwidth($firstLine, 0, 80, '...') : 'Notification',
            'body' => $body,
            'link' => $this->rewriteLink($notification, $stores),
            'link_text' => null,
            'read_at' => ($notification['seen'] ?? false) ? $created : null,
            'created_at' => $created,
        ];
    }

    /**
     * @param  array<string, mixed>  $notification
     * @param  array<string, string>  $stores
     */
    private function rewriteLink(array $notification, array $stores): ?string
    {
        $link = trim($notification['link'] ?? '');
        if (preg_match('/[\x00-\x20\x7f]/', $link) || str_contains($link, '\\')) {
            return null;
        }
        $base = rtrim((string) config('services.btcpay.base_url'), '/');
        $path = $link;
        if ($base !== '' && str_starts_with($link, $base.'/')) {
            $path = substr($link, strlen($base));
        } elseif (preg_match('#^https?://#i', $link)) {
            $url = parse_url($link);

            return $this->remoteCategory($notification) === 'invoices' || ! is_array($url) || empty($url['host'])
                || isset($url['user']) || isset($url['pass']) ? null : $link;
        } elseif ($link !== '' && (str_starts_with($link, '//') || parse_url($link, PHP_URL_SCHEME) !== null)) {
            return null;
        } elseif ($path !== '' && ! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }
        $path = explode('?', $path, 2)[0];
        $storeId = $notification['storeId'] ?? null;
        $invoiceId = null;
        if (preg_match('#^/stores/([a-zA-Z0-9_-]+)/invoices/([a-zA-Z0-9_-]+)(?:/.*)?$#', $path, $matches)) {
            if ($storeId !== null && $storeId !== $matches[1]) {
                return null;
            }
            $storeId = $matches[1];
            $invoiceId = $matches[2];
        } elseif (preg_match('#^/(?:invoices|i)/([a-zA-Z0-9_-]+)$#', $path, $matches)) {
            $invoiceId = $matches[1];
        }
        $localId = $storeId !== null ? ($stores[$storeId] ?? null) : null;
        if ($localId === null) {
            return null;
        }
        $panel = rtrim((string) config('app.url'), '/');
        if ($invoiceId !== null) {
            return $panel.'/stores/'.$localId.'/invoices/'.$invoiceId;
        }

        return $this->remoteCategory($notification) === 'invoices' ? $panel.'/stores/'.$localId.'?section=invoices' : null;
    }
}
