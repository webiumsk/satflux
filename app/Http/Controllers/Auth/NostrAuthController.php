<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\NostrAuthChallenge;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Services\BtcPay\UserService;
use App\Services\Nostr\NostrEventVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class NostrAuthController extends Controller
{
    public function __construct(
        protected UserService $userService,
        protected NostrEventVerifier $verifier
    ) {}

    public function challenge(Request $request)
    {
        if (! config('services.nostr_auth.enabled', false)) {
            return response()->json(['error' => 'Nostr auth is not enabled'], 403);
        }

        $id = bin2hex(random_bytes(32));
        $ttl = config('services.nostr_auth.challenge_ttl_seconds', 300);

        NostrAuthChallenge::create([
            'id' => $id,
            'expires_at' => now()->addSeconds($ttl),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'challenge_id' => $id,
            'message' => "Sign this message to login: {$id}",
        ]);
    }

    public function verify(Request $request)
    {
        if (! config('services.nostr_auth.enabled', false)) {
            return response()->json(['error' => 'Nostr auth is not enabled'], 403);
        }

        $request->validate([
            'challenge_id' => ['required', 'string', 'size:64'],
            'event' => ['required', 'array'],
            'event.pubkey' => ['required', 'string', 'size:64'],
            'event.id' => ['required', 'string', 'size:64'],
            'event.sig' => ['required', 'string', 'size:128'],
            'event.kind' => ['required', 'integer'],
            'event.created_at' => ['required', 'integer'],
            'event.tags' => ['nullable', 'array'],
            'event.content' => ['required', 'string'],
        ]);

        $challengeId = $request->input('challenge_id');
        $event = $request->input('event');

        $challenge = NostrAuthChallenge::find($challengeId);
        if (! $challenge) {
            return response()->json(['error' => 'Challenge not found'], 404);
        }
        if ($challenge->isExpired()) {
            return response()->json(['error' => 'Challenge expired'], 400);
        }
        if ($challenge->isConsumed()) {
            return response()->json(['error' => 'Challenge already used'], 400);
        }

        if (! hash_equals($challengeId, $event['content'])) {
            return response()->json(['error' => 'Event content does not match challenge'], 400);
        }

        if (! $this->verifier->verify($event)) {
            Log::warning('Nostr event verification failed', ['challenge_id' => $challengeId]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $pubkey = $event['pubkey'];

        if ($challenge->link_user_id) {
            if ($challenge->purpose === 'reveal') {
                Cache::put('reveal_confirmed:' . $challenge->link_user_id, true, now()->addSeconds(120));
                $challenge->update(['consumed_at' => now(), 'nostr_public_key' => $pubkey]);

                return response()->json(['status' => 'OK']);
            }
            if ($challenge->purpose === 'link') {
                $existing = User::where('nostr_public_key', $pubkey)->where('id', '!=', $challenge->link_user_id)->first();
                if ($existing) {
                    return response()->json(['error' => 'Key already linked to another account.'], 400);
                }
                User::where('id', $challenge->link_user_id)->update(['nostr_public_key' => $pubkey]);
                $challenge->update(['consumed_at' => now(), 'nostr_public_key' => $pubkey]);

                return response()->json(['status' => 'OK']);
            }
        }

        $user = User::where('nostr_public_key', $pubkey)->first();
        if ($user) {
            if ($user->hasVerifiedEmail()) {
                $challenge->update(['consumed_at' => now(), 'nostr_public_key' => $pubkey]);

                return response()->json(['status' => 'OK']);
            }
            $challenge->update(['consumed_at' => now(), 'pending_user_id' => $user->id]);

            return response()->json(['status' => 'OK']);
        }

        $challenge->update(['consumed_at' => now(), 'nostr_public_key' => $pubkey]);

        return response()->json(['status' => 'OK']);
    }

    public function challengeStatus(Request $request, string $id)
    {
        $noCache = fn ($response) => $response
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');

        if (! config('services.nostr_auth.enabled', false)) {
            return $noCache(response()->json(['error' => 'Nostr auth is not enabled'], 403));
        }

        $challenge = NostrAuthChallenge::find($id);
        if (! $challenge) {
            return $noCache(response()->json(['status' => 'error', 'message' => 'Challenge not found'], 404));
        }
        if ($challenge->isExpired()) {
            return $noCache(response()->json(['status' => 'expired']));
        }
        if (! $challenge->isConsumed()) {
            return $noCache(response()->json(['status' => 'pending']));
        }

        if ($challenge->pending_user_id) {
            $user = User::find($challenge->pending_user_id);
            if ($user && ! $user->hasVerifiedEmail()) {
                return $noCache(response()->json([
                    'status' => 'pending_email',
                    'user_id' => $user->id,
                ]));
            }
        }

        if ($challenge->link_user_id) {
            if ($challenge->purpose === 'reveal') {
                return $noCache(response()->json(['status' => 'reveal_confirmed']));
            }
            $linkUser = User::find($challenge->link_user_id);

            return $noCache(response()->json(['status' => 'linked', 'user' => $linkUser]));
        }

        if ($challenge->nostr_public_key) {
            $userByKey = User::where('nostr_public_key', $challenge->nostr_public_key)->first();
            if ($userByKey && $userByKey->hasVerifiedEmail()) {
                Auth::login($userByKey);
                $request->session()->regenerate();
                $userByKey->update(['last_login_at' => now()]);

                return $noCache(response()->json([
                    'status' => 'authenticated',
                    'user' => $userByKey,
                ]));
            }

            return $noCache(response()->json([
                'status' => 'pending_email',
                'challenge_id' => $challenge->id,
            ]));
        }

        return $noCache(response()->json(['status' => 'pending']));
    }

    public function linkChallenge(Request $request)
    {
        if (! config('services.nostr_auth.enabled', false)) {
            return response()->json(['error' => 'Nostr auth is not enabled'], 403);
        }

        $user = $request->user();
        if ($user->nostr_public_key) {
            return response()->json(['error' => __('auth.nostr_key_already_registered')], 400);
        }

        $id = bin2hex(random_bytes(32));
        $ttl = config('services.nostr_auth.challenge_ttl_seconds', 300);

        NostrAuthChallenge::create([
            'id' => $id,
            'expires_at' => now()->addSeconds($ttl),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'link_user_id' => $user->id,
            'purpose' => 'link',
        ]);

        return response()->json([
            'challenge_id' => $id,
            'message' => "Sign this message to link your account: {$id}",
        ]);
    }

    public function revealConfirmChallenge(Request $request)
    {
        if (! config('services.nostr_auth.enabled', false)) {
            return response()->json(['error' => 'Nostr auth is not enabled'], 403);
        }

        $user = $request->user();
        if (! $user->nostr_public_key) {
            return response()->json(['error' => 'Nostr login required to confirm via Nostr.'], 400);
        }

        $id = bin2hex(random_bytes(32));
        $ttl = config('services.nostr_auth.challenge_ttl_seconds', 300);

        NostrAuthChallenge::create([
            'id' => $id,
            'expires_at' => now()->addSeconds($ttl),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'link_user_id' => $user->id,
            'purpose' => 'reveal',
        ]);

        return response()->json([
            'challenge_id' => $id,
            'message' => "Sign this message to confirm reveal: {$id}",
        ]);
    }

    public function completeRegistration(Request $request)
    {
        if (! config('services.nostr_auth.enabled', false)) {
            return response()->json(['error' => 'Nostr auth is not enabled'], 403);
        }

        $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'challenge_id' => ['nullable', 'string', 'size:64'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
        ]);

        $userId = $request->input('user_id');
        $challengeId = $request->input('challenge_id');
        if ($userId && $challengeId) {
            return response()->json(['error' => 'Provide either user_id or challenge_id, not both.'], 422);
        }
        if (! $userId && ! $challengeId) {
            return response()->json(['error' => 'Provide user_id or challenge_id.'], 422);
        }

        $user = null;
        $publicKey = null;

        if ($userId) {
            $user = User::findOrFail($userId);
            if (! $user->nostr_public_key) {
                return response()->json(['error' => 'Invalid user'], 400);
            }
            $publicKey = $user->nostr_public_key;
        } else {
            $challenge = NostrAuthChallenge::find($challengeId);
            if (! $challenge || ! $challenge->isConsumed() || ! $challenge->nostr_public_key) {
                return response()->json(['error' => 'Invalid or expired challenge.'], 400);
            }
            $publicKey = $challenge->nostr_public_key;
        }

        try {
            if ($this->userService->checkEmailExists($request->email)) {
                throw ValidationException::withMessages([
                    'email' => ['This email is already registered on BTCPay Server.'],
                ]);
            }
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            Log::warning('BTCPay email check failed during Nostr auth registration', [
                'email' => $request->email,
                'error' => $e->getMessage(),
            ]);
        }

        $existingUser = User::where('email', $request->email)->first();
        if ($existingUser) {
            if ($existingUser->hasVerifiedEmail()) {
                throw ValidationException::withMessages([
                    'email' => ['The email has already been taken.'],
                ]);
            }
            $existingUser->update(['nostr_public_key' => $publicKey]);
            if ($user && $user->id !== $existingUser->id) {
                $user->delete();
            }
            $user = $existingUser;
            if ($challengeId) {
                $ch = NostrAuthChallenge::find($challengeId);
                if ($ch) {
                    $ch->update(['pending_user_id' => $existingUser->id]);
                }
            }
        } elseif ($user) {
            $user->update(['email' => $request->email]);
        } else {
            $existingByKey = User::where('nostr_public_key', $publicKey)->first();
            if ($existingByKey) {
                if ($existingByKey->hasVerifiedEmail()) {
                    throw ValidationException::withMessages([
                        'email' => [__('auth.nostr_key_already_registered')],
                    ]);
                }
                $user = $existingByKey;
                $user->update(['email' => $request->email]);
                $ch = NostrAuthChallenge::find($challengeId);
                if ($ch) {
                    $ch->update(['pending_user_id' => $user->id]);
                }
            } else {
                $user = User::create([
                    'email' => $request->email,
                    'password' => Hash::make(Str::random(32)),
                    'nostr_public_key' => $publicKey,
                ]);
                $ch = NostrAuthChallenge::find($challengeId);
                if ($ch) {
                    $ch->update(['pending_user_id' => $user->id]);
                }
            }
        }

        $verificationUrl = $this->verificationUrl($user);
        try {
            $user->notify(new VerifyEmailNotification($verificationUrl));
        } catch (TransportExceptionInterface $e) {
            Log::warning('Failed to send verification email (NostrAuth)', [
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
            throw ValidationException::withMessages([
                'email' => [__('messages.verification_email_failed')],
            ]);
        }

        return response()->json([
            'message' => 'Email saved. Please check your email to verify your account.',
            'user_id' => $user->id,
        ]);
    }

    public function checkEmailExists(Request $request)
    {
        if (! config('services.nostr_auth.enabled', false)) {
            return response()->json(['error' => 'Nostr auth is not enabled'], 403);
        }
        $request->validate(['email' => ['required', 'email']]);
        try {
            $exists = $this->userService->checkEmailExists($request->email);

            return response()->json(['exists' => $exists]);
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            Log::error('BTCPay email check failed', ['email' => $request->email, 'error' => $e->getMessage()]);

            return response()->json(['exists' => false, 'error' => 'Could not verify email availability'], 500);
        }
    }

    public function enabled()
    {
        return response()
            ->json(['enabled' => config('services.nostr_auth.enabled', false)])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    protected function verificationUrl($user)
    {
        $baseUrl = rtrim(config('app.url', env('APP_URL', 'http://localhost:8080')), '/');
        URL::forceRootUrl($baseUrl);
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        return str_replace('/api/auth/verify-email/', '/auth/verify-email/', $url);
    }
}
