<?php

namespace App\Services;

use App\Exceptions\EmailCodeChallengeException;
use App\Models\AuditLog;
use App\Models\EmailVerificationChallenge;
use App\Models\Store;
use App\Models\User;
use App\Services\Auth\EmailCodeChallengeService;
use App\Services\BtcPay\BtcPayClient;
use App\Services\BtcPay\Exceptions\BtcPayException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CashuChangeRecoveryService
{
    public const HEADERS = [
        'Cache-Control' => 'private, no-store, no-cache, max-age=0',
        'Pragma' => 'no-cache',
        'X-Content-Type-Options' => 'nosniff',
    ];

    private const AMOUNTS = ['pendingSat' => 'pending_sat', 'sweptSat' => 'swept_sat', 'minimumSweepSat' => 'minimum_sweep_sat'];

    private const COUNTS = [
        'unresolvedRecords' => 'unresolved_records', 'availableRecords' => 'available_records',
        'exportedRecords' => 'exported_records', 'sweepingRecords' => 'sweeping_records',
        'manualReviewRecords' => 'manual_review_records', 'unsupportedUnitRecords' => 'unsupported_unit_records',
        'otherUnresolvedRecords' => 'other_unresolved_records',
    ];

    public function __construct(protected BtcPayClient $client, protected EmailCodeChallengeService $challenges) {}

    public function canRecover(Store $store, User $user): bool
    {
        return (string) $store->user_id === (string) $user->id && ! $user->is_guest && $user->hasVerifiedEmail();
    }

    public function assertOwner(Store $store, User $user): void
    {
        abort_unless($this->canRecover($store, $user), 403);
    }

    public function summary(Store $store, User $user): array
    {
        try {
            $owner = $store->user;
            if (! $owner instanceof User) {
                return $this->emptySummary('unavailable');
            }
            $raw = $this->client->withUserKey($owner->getBtcPayApiKeyOrFail(),
                fn () => $this->client->get($this->endpoint($store)));
        } catch (BtcPayException $e) {
            return $this->emptySummary($e->getStatusCode() === 404 ? 'unsupported' : 'unavailable');
        } catch (\Throwable) {
            return $this->emptySummary('unavailable');
        }
        if (($raw['version'] ?? null) !== 1) {
            return $this->emptySummary('unavailable');
        }
        $summary = ['status' => 'available', 'version' => 1, 'can_recover' => $this->canRecover($store, $user)];
        foreach (self::AMOUNTS as $remote => $local) {
            $value = $raw[$remote] ?? null;
            if (! is_string($value) || ! preg_match('/^(0|[1-9][0-9]{0,18})$/D', $value)
                || (strlen($value) === 19 && strcmp($value, '9223372036854775807') > 0)) {
                return $this->emptySummary('unavailable');
            }
            $summary[$local] = $value;
        }
        foreach (self::COUNTS as $remote => $local) {
            $value = $raw[$remote] ?? null;
            if (! is_int($value) || $value < 0 || $value > 2147483647) {
                return $this->emptySummary('unavailable');
            }
            $summary[$local] = $value;
        }

        return $summary;
    }

    private function emptySummary(string $status): array
    {
        return ['status' => $status, 'version' => null, 'can_recover' => false,
            ...array_fill_keys(array_values(self::AMOUNTS), null),
            ...array_fill_keys(array_values(self::COUNTS), null)];
    }

    public function requestConfirmation(Store $store, User $user, string $action): array
    {
        $this->assertOwner($store, $user);
        $this->assertAction($action);
        $challenge = $this->challenges->issue($user, EmailVerificationChallenge::PURPOSE_CASHU_CHANGE_RECOVERY,
            (string) $user->email, ['store_id' => $store->id, 'action' => $action]);

        return ['challenge' => $this->challenges->summary($challenge)];
    }

    public function resendConfirmation(Store $store, User $user, string $action): array
    {
        $this->assertOwner($store, $user);
        $this->assertAction($action);

        return DB::transaction(function () use ($store, $user, $action) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->assertBinding($this->challenges->active($user, EmailVerificationChallenge::PURPOSE_CASHU_CHANGE_RECOVERY), $store, $action);
            $challenge = $this->challenges->resend($user, EmailVerificationChallenge::PURPOSE_CASHU_CHANGE_RECOVERY);

            return ['challenge' => $this->challenges->summary($challenge)];
        });
    }

    /** Consume the exact authorization in a committed transaction before any provider request. */
    public function perform(Store $store, User $user, string $action, string $code): array
    {
        $this->assertAction($action);

        return Cache::lock('wallet-update:'.$store->id, 1800)->block(10, function () use ($store, $user, $action, $code) {
            $store = $store->fresh();
            $user = $user->fresh();
            abort_unless($store instanceof Store && $user instanceof User, 403);
            $this->assertOwner($store, $user);
            $pending = EmailVerificationChallenge::where('user_id', $user->id)
                ->where('purpose', EmailVerificationChallenge::PURPOSE_CASHU_CHANGE_RECOVERY)->live()->latest('created_at')->first();
            $this->assertBinding($pending, $store, $action);
            // verify() commits failed-attempt counters itself. Do not wrap it in a
            // transaction that would roll those counters back when it rejects.
            $verified = $this->challenges->verify($user, EmailVerificationChallenge::PURPOSE_CASHU_CHANGE_RECOVERY, $code);
            [$user, $store] = DB::transaction(function () use ($verified, $store, $user, $action) {
                // Issuance also locks the user first. Recheck eligibility and the
                // store binding at the point of consumption, not an earlier read.
                $current = User::whereKey($user->id)->lockForUpdate()->first();
                $lockedStore = Store::whereKey($store->id)->lockForUpdate()->first();
                abort_unless($current instanceof User && $lockedStore instanceof Store, 403);
                $this->assertOwner($lockedStore, $current);
                $exact = EmailVerificationChallenge::whereKey($verified->id)->where('user_id', $current->id)
                    ->where('email', strtolower(trim((string) $current->email)))
                    ->where('purpose', EmailVerificationChallenge::PURPOSE_CASHU_CHANGE_RECOVERY)->lockForUpdate()->first();
                $this->assertBinding($exact, $lockedStore, $action);
                if (! $exact->isLive() || $exact->verified_at === null) {
                    throw EmailCodeChallengeException::missing();
                }
                if ($exact->isExpired()) {
                    throw EmailCodeChallengeException::expired();
                }
                $this->challenges->consume($exact);
                AuditLog::log('cashu.change_recovery_authorized', 'store', $lockedStore->id,
                    ['action' => $action, 'challenge_id' => $exact->id], $current->id);

                return [$current, $lockedStore];
            });
            try {
                $key = $user->getBtcPayApiKeyOrFail();
                $result = $this->client->withUserKey($key,
                    fn () => $this->client->post($this->endpoint($store).'/'.$action, ['confirmed' => true], retry: false));
            } catch (BtcPayException $e) {
                if ($e->getStatusCode() === 404) {
                    $this->fail('cashu_change_unsupported', 409);
                }
                $this->fail($e->getStatusCode() >= 400 && $e->getStatusCode() < 500 ? 'cashu_change_recovery_refused' : 'cashu_change_unavailable',
                    $e->getStatusCode() >= 400 && $e->getStatusCode() < 500 ? 422 : 502);
            } catch (\Throwable) {
                $this->fail('cashu_change_unavailable', 502);
            }
            if ($action === 'verify') {
                if (($result['verified'] ?? null) !== true) {
                    $this->fail('cashu_change_unavailable', 502);
                }

                return ['verified' => true];
            }
            $tokens = $result['tokens'] ?? null;
            if (! is_array($tokens) || ! array_is_list($tokens)) {
                $this->fail('cashu_change_unavailable', 502);
            }
            if ($tokens === []) {
                $this->fail('cashu_change_empty', 409);
            }
            foreach ($tokens as $token) {
                if (! is_string($token) || $token === '') {
                    $this->fail('cashu_change_unavailable', 502);
                }
            }

            return $tokens;
        });
    }

    /** @phpstan-assert EmailVerificationChallenge $challenge */
    private function assertBinding(?EmailVerificationChallenge $challenge, Store $store, string $action): void
    {
        if (! $challenge || ($challenge->payload['store_id'] ?? null) !== $store->id || ($challenge->payload['action'] ?? null) !== $action) {
            throw EmailCodeChallengeException::missing();
        }
    }

    private function assertAction(string $action): void
    {
        abort_unless(in_array($action, ['export', 'verify'], true), 422);
    }

    private function endpoint(Store $store): string
    {
        return '/api/v1/stores/'.rawurlencode((string) $store->btcpay_store_id).'/plugins/cashumelt/change';
    }

    private function fail(string $code, int $status): never
    {
        $messages = [
            'cashu_change_unsupported' => 'Cashu change recovery is not supported by the installed provider.',
            'cashu_change_unavailable' => 'Cashu change recovery is unavailable. Request a new code before trying again.',
            'cashu_change_recovery_refused' => 'Cashu change recovery could not be completed. No wallet-change permission was granted.',
            'cashu_change_empty' => 'No recoverable change tokens were returned. The wallet-change safety check remains independent.',
        ];
        throw new HttpResponseException(response()->json(['code' => $code, 'message' => $messages[$code]], $status, self::HEADERS));
    }
}
