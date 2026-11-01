<?php

namespace App\Services\Invoicing;

use App\Models\Store;
use App\Support\Invoicing\BankMimeDiagnosticRunner;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/** One private, encrypted diagnostic slot. Never a financial replay mechanism. */
class BankMimeCaptureService
{
    public const KEY = 'bank-mail:private-capture:v1';

    public const MAX_ENCRYPTED_BYTES = 1048576;

    private const ARM = <<<'LUA'
redis.call('PEXPIRETIME', KEYS[1])
local now = redis.call('TIME')
local milliseconds = tonumber(now[1]) * 1000 + math.floor(tonumber(now[2]) / 1000)
if tonumber(ARGV[2]) <= milliseconds or tonumber(ARGV[2]) > milliseconds + 7200000 then return 0 end
return redis.call('SET', KEYS[1], ARGV[1], 'NX', 'PXAT', ARGV[2]) and 1 or 0
LUA;

    private const READ = <<<'LUA'
local value = redis.call('GET', KEYS[1])
if not value then return {} end
local now = redis.call('TIME')
return {value, redis.call('PEXPIRETIME', KEYS[1]), tonumber(now[1]) * 1000 + math.floor(tonumber(now[2]) / 1000)}
LUA;

    private const CAPTURE = <<<'LUA'
local current = redis.call('GET', KEYS[1])
if not current or current ~= ARGV[1] then return 0 end
local expiry = redis.call('PEXPIRETIME', KEYS[1])
local now = redis.call('TIME')
if expiry ~= tonumber(ARGV[3]) or expiry <= tonumber(now[1]) * 1000 + math.floor(tonumber(now[2]) / 1000) then return 0 end
return redis.call('SET', KEYS[1], ARGV[2], 'XX', 'PXAT', expiry) and 1 or 0
LUA;

    public function __construct(private BankMimeDiagnosticRunner $runner, private BankInboundAddressService $addresses) {}

    public function arm(Store $store, int $minutes): string
    {
        if (! $this->enabled()) {
            return 'disabled';
        }
        if ($minutes < 1 || $minutes > 120) {
            return 'invalid_window';
        }
        try {
            if (! $store->exists || ! Str::isUuid($store->id)
                || ! Store::query()->whereKey($store->id)->where('user_id', $store->user_id)->exists()) {
                return 'invalid_store';
            }
            $time = $this->redis()->time();
            $now = (int) $time[0] * 1000 + intdiv((int) $time[1], 1000);
            $slot = [
                'version' => 1, 'state' => 'ARMED', 'generation' => bin2hex(random_bytes(16)),
                'store_id' => $store->id, 'owner_user_id' => (string) $store->user_id,
                'armed_at' => $now, 'expires_at' => $now + $minutes * 60000,
            ];
            $encrypted = $this->encrypt($slot);
            if ($encrypted === null) {
                return 'oversized';
            }

            return $this->evaluate(self::ARM, $encrypted, $slot['expires_at']) === 1
                ? 'armed' : 'occupied';
        } catch (\Throwable) {
            return 'unavailable';
        }
    }

    /** An opaque compare-and-swap token; no token is created when writing is off. */
    public function armedFor(Store $store): ?string
    {
        if (! $this->enabled()) {
            return null;
        }
        try {
            $read = $this->read();
            if (($read['slot']['state'] ?? null) !== 'ARMED' || ! $this->sameOwner($read['slot'], $store)) {
                return null;
            }

            return $read['ciphertext'];
        } catch (\Throwable) {
            return null;
        }
    }

    /** Save only an eligible rejection, and only by replacing its existing ARMED ciphertext. */
    public function capture(Store $store, string $token, string $rawMime, string $recipient, array $summary): string
    {
        if (! $this->enabled()) {
            return 'disabled';
        }
        try {
            $read = $this->read();
            if (($read['slot']['state'] ?? null) !== 'ARMED' || ! hash_equals($read['ciphertext'], $token)
                || ! $this->sameOwner($read['slot'], $store)) {
                return 'not_armed';
            }
            if (strlen($rawMime) > 262144) {
                return 'oversized';
            }
            $safe = $this->runner->sanitizeSummary($summary);
            if ($safe === null || $safe['status'] !== 'invalid' || $safe['reason'] !== 'invalid_signature') {
                return 'invalid_summary';
            }
            if (! $this->validRecipient($recipient, $store->id, (string) $store->user_id)) {
                return 'owner_mismatch';
            }
            $slot = $read['slot'] + [
                'captured_at' => $read['now'], 'raw_mime_base64' => base64_encode($rawMime),
                'recipient' => $recipient, 'content_sha256' => hash('sha256', $rawMime), 'original_summary' => $safe,
            ];
            $slot['state'] = 'CAPTURED';
            $encrypted = $this->encrypt($slot);
            if ($encrypted === null) {
                return 'oversized';
            }
            // A disable while verification was running must still stop retention.
            if (! $this->enabled()) {
                return 'disabled';
            }

            return $this->evaluate(self::CAPTURE, $token, $encrypted, $slot['expires_at']) === 1
                ? 'captured' : 'not_armed';
        } catch (\Throwable) {
            return 'unavailable';
        }
    }

    /** Output contains no owner, recipient, generation or message identifiers. */
    public function status(): array
    {
        try {
            $read = $this->read();
            $status = $read['status'] === 'valid' ? strtolower($read['slot']['state']) : $read['status'];
        } catch (\Throwable) {
            $status = 'unavailable';
        }

        return ['version' => 1, 'status' => $status, 'writing_enabled' => (bool) config('bank_mail_capture.enabled', false)];
    }

    /** Read-only verification, without intake/import/provider/audit side effects. */
    public function diagnose(): array
    {
        try {
            $read = $this->read();
            if ($read['status'] !== 'valid') {
                return ['version' => 1, 'status' => $read['status']];
            }
            $slot = $read['slot'];
            if ($slot['state'] !== 'CAPTURED') {
                return ['version' => 1, 'status' => 'not_captured'];
            }
            $original = $slot['original_summary'];
            try {
                $recheck = $this->runner->run(base64_decode($slot['raw_mime_base64'], true), $slot['recipient']);
                $current = $recheck['diagnostics'];
            } catch (\Throwable) {
                return $this->stillCaptured($read['ciphertext'])
                    ? ['version' => 1, 'status' => 'recheck_unavailable', 'original' => $original]
                    : ['version' => 1, 'status' => 'empty'];
            }
            if (! $this->stillCaptured($read['ciphertext'])) {
                return ['version' => 1, 'status' => 'empty'];
            }

            return ['version' => 1, 'status' => 'diagnosed', 'original' => $original, 'recheck' => $current];
        } catch (\Throwable) {
            return ['version' => 1, 'status' => 'unavailable'];
        }
    }

    private function sameOwner(array $slot, Store $store): bool
    {
        return $slot['store_id'] === $store->id && $slot['owner_user_id'] === (string) $store->user_id;
    }

    private function validRecipient(string $recipient, string $storeId, string $userId): bool
    {
        if (strlen($recipient) > 255 || ! preg_match('/^[a-zA-Z0-9.!#$%&\x27*+\/=?^_`{|}~-]+@[a-zA-Z0-9.-]+$/D', $recipient)) {
            return false;
        }
        $owner = $this->addresses->resolveOwner($recipient);

        return $owner instanceof Store && $owner->id === $storeId && (string) $owner->user_id === $userId;
    }

    private function encrypt(array $slot): ?string
    {
        $encrypted = Crypt::encryptString(json_encode($slot, JSON_THROW_ON_ERROR));

        return strlen($encrypted) <= self::MAX_ENCRYPTED_BYTES ? $encrypted : null;
    }

    private function stillCaptured(string $ciphertext): bool
    {
        $read = $this->read();

        return ($read['slot']['state'] ?? null) === 'CAPTURED' && hash_equals($ciphertext, $read['ciphertext']);
    }

    /** @return array{status: string, slot?: array, ciphertext?: string, now?: int} */
    private function read(): array
    {
        $read = $this->evaluate(self::READ);
        if ($read === []) {
            return ['status' => 'empty'];
        }
        if (! is_array($read) || count($read) !== 3 || ! is_string($read[0])
            || strlen($read[0]) > self::MAX_ENCRYPTED_BYTES) {
            return ['status' => 'invalid_record'];
        }
        try {
            $slot = json_decode(Crypt::decryptString($read[0]), true, 16, JSON_THROW_ON_ERROR);
            if (! $this->validSlot($slot, (int) $read[1], (int) $read[2])) {
                return ['status' => 'invalid_record'];
            }
        } catch (\Throwable) {
            return ['status' => 'invalid_record'];
        }

        return ['status' => 'valid', 'slot' => $slot, 'ciphertext' => $read[0], 'now' => (int) $read[2]];
    }

    private function validSlot(mixed $slot, int $redisExpiry, int $now): bool
    {
        if (! is_array($slot) || ($slot['version'] ?? null) !== 1
            || ! in_array($slot['state'] ?? null, ['ARMED', 'CAPTURED'], true)
            || ! is_string($slot['generation'] ?? null) || ! preg_match('/^[a-f0-9]{32}$/D', $slot['generation'])
            || ! is_string($slot['store_id'] ?? null) || ! Str::isUuid($slot['store_id'])
            || ! is_string($slot['owner_user_id'] ?? null) || ! preg_match('/^[1-9][0-9]*$/D', $slot['owner_user_id'])
            || ! is_int($slot['armed_at'] ?? null) || ! is_int($slot['expires_at'] ?? null)
            || $slot['armed_at'] > $now || $slot['expires_at'] <= $now
            || $slot['expires_at'] <= $slot['armed_at'] || $slot['expires_at'] - $slot['armed_at'] > 7200000
            || $slot['expires_at'] !== $redisExpiry) {
            return false;
        }
        $keys = ['version', 'state', 'generation', 'store_id', 'owner_user_id', 'armed_at', 'expires_at'];
        if ($slot['state'] === 'CAPTURED') {
            $keys = array_merge($keys, ['captured_at', 'raw_mime_base64', 'recipient', 'content_sha256', 'original_summary']);
            if (! is_int($slot['captured_at'] ?? null) || $slot['captured_at'] < $slot['armed_at']
                || $slot['captured_at'] > $now || $slot['captured_at'] >= $slot['expires_at']
                || ! is_string($slot['raw_mime_base64'] ?? null) || strlen($slot['raw_mime_base64']) > 349528
                || ! is_string($slot['recipient'] ?? null) || ! is_string($slot['content_sha256'] ?? null)) {
                return false;
            }
            $raw = base64_decode($slot['raw_mime_base64'], true);
            $safe = $this->runner->sanitizeSummary($slot['original_summary'] ?? null);
            if ($raw === false || strlen($raw) > 262144 || ! hash_equals(hash('sha256', $raw), $slot['content_sha256'])
                || $safe === null || $safe !== $slot['original_summary'] || $safe['status'] !== 'invalid' || $safe['reason'] !== 'invalid_signature'
                || ! $this->validRecipient($slot['recipient'], $slot['store_id'], $slot['owner_user_id'])) {
                return false;
            }
        }

        return count($slot) === count($keys) && array_diff(array_keys($slot), $keys) === [];
    }

    private function redis(): Connection
    {
        return Redis::connection((string) config('bank_mail_capture.redis_connection', 'cache'));
    }

    private function evaluate(string $script, mixed ...$arguments): mixed
    {
        $redis = $this->redis();
        if ($redis instanceof PhpRedisConnection) {
            return $redis->eval($script, 1, self::KEY, ...$arguments);
        }

        return $redis->command('eval', [$script, 1, self::KEY, ...$arguments]);
    }

    /** @phpstan-impure */
    private function enabled(): bool
    {
        return (bool) config('bank_mail_capture.enabled', false);
    }
}
