<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LnurlAuthChallenge;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Services\BtcPay\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use kornrunner\Secp256k1;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class LnurlAuthController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Generate a LNURL-auth challenge.
     */
    public function challenge(Request $request)
    {
        if (! config('services.lnurl_auth.enabled', false)) {
            return response()->json(['error' => 'LNURL-auth is not enabled'], 403);
        }

        $k1 = bin2hex(random_bytes(32));
        $domain = rtrim(config('services.lnurl_auth.domain') ?: config('app.url'), '/');
        if (! preg_match('#^https?://#', $domain)) {
            $domain = 'https://'.$domain;
        }

        LnurlAuthChallenge::create([
            'k1' => $k1,
            'expires_at' => now()->addMinutes(5),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $lnurlAuthUrl = "{$domain}/api/lnurl-auth/verify?tag=login&k1={$k1}&action=login";

        return response()->json([
            'k1' => $k1,
            'lnurl' => $lnurlAuthUrl,
            'qr' => $lnurlAuthUrl, // QR code data URL can be generated on frontend
        ]);
    }

    /**
     * LNURL-auth endpoint - handles both:
     * 1. Initial GET (no sig/key): Returns LUD-04 auth params so wallet knows how to proceed.
     * 2. Callback GET (with sig/key): Verifies signature and completes login/register.
     */
    public function verify(Request $request)
    {
        if (! config('services.lnurl_auth.enabled', false)) {
            return response()->json(['error' => 'LNURL-auth is not enabled'], 403);
        }

        $k1 = $request->input('k1');
        $signature = $request->input('sig');
        $publicKey = $request->input('key');

        // Phase 1: Wallet fetches URL first - return LUD-04 auth params (no sig/key yet)
        if (empty($signature) || empty($publicKey)) {
            if (! $k1) {
                return response()->json(['status' => 'ERROR', 'reason' => 'Missing k1'], 200);
            }

            $challenge = LnurlAuthChallenge::find($k1);
            if (! $challenge) {
                return response()->json(['status' => 'ERROR', 'reason' => 'Invalid challenge'], 200);
            }
            if ($challenge->isExpired()) {
                return response()->json(['status' => 'ERROR', 'reason' => 'Challenge expired'], 200);
            }

            $domain = rtrim(config('services.lnurl_auth.domain') ?: config('app.url'), '/');
            if (! preg_match('#^https?://#', $domain)) {
                $domain = 'https://'.$domain;
            }
            $callbackUrl = "{$domain}/api/lnurl-auth/verify?tag=login&k1={$k1}&action=login";

            return response()->json([
                'tag' => 'login',
                'k1' => $k1,
                'callback' => $callbackUrl,
                'minVersion' => '1',
            ]);
        }

        // Phase 2: Wallet sends signed response - verify and complete auth
        $request->validate([
            'k1' => ['required', 'string', 'size:64'],
            'sig' => ['required', 'string'],
            'key' => ['required', 'string'],
        ]);

        // Find challenge
        $challenge = LnurlAuthChallenge::find($k1);
        
        if (! $challenge) {
            return response()->json(['status' => 'ERROR', 'reason' => 'Invalid challenge'], 200);
        }

        if ($challenge->isExpired()) {
            return response()->json(['status' => 'ERROR', 'reason' => 'Challenge expired'], 200);
        }

        if ($challenge->isConsumed()) {
            return response()->json(['status' => 'ERROR', 'reason' => 'Challenge already used'], 200);
        }

        // Verify signature (LUD-04: wallet signs the raw 32-byte k1 as digest; sends DER-encoded sig)
        try {
            $secp256k1 = new Secp256k1();
            // kornrunner verify($hashHex, $signature, $publicKeyHex): hash and key must be hex strings
            // Signature from wallet is DER; kornrunner expects 128-char flat hex (r||s)
            $signatureFlatHex = $this->derSignatureToFlatHex($signature);
            $isValid = $secp256k1->verify($k1, $signatureFlatHex, $publicKey);

            if (! $isValid) {
                Log::warning('LNURL-auth signature verification failed', [
                    'k1' => $k1,
                    'ip' => $request->ip(),
                ]);
                return response()->json(['status' => 'ERROR', 'reason' => 'Invalid signature'], 200);
            }
        } catch (\Exception $e) {
            Log::error('LNURL-auth signature verification error', [
                'error' => $e->getMessage(),
                'k1' => $k1,
                'ip' => $request->ip(),
            ]);
            return response()->json(['status' => 'ERROR', 'reason' => 'Signature verification failed'], 200);
        }

        // Find user by lightning public key (including unverified users)
        $user = User::where('lightning_public_key', $publicKey)->first();
        
        if ($user) {
            // User exists
            if ($user->hasVerifiedEmail()) {
                // User is verified - login and return success
                Auth::login($user);
                $request->session()->regenerate();
                $user->update(['last_login_at' => now()]);

                // Mark challenge as consumed (user is authenticated)
                $challenge->update([
                    'consumed_at' => now(),
                ]);

                return response()->json([
                    'status' => 'OK',
                ]);
            } else {
                // User exists but email not verified - store user_id in challenge
                $challenge->update([
                    'consumed_at' => now(),
                    'pending_user_id' => $user->id,
                ]);

                return response()->json([
                    'status' => 'OK',
                ]);
            }
        } else {
            // New key: do not create user yet. Store public key on challenge;
            // user is created in completeRegistration() after email is validated (unique in DB + BTCPay).
            $challenge->update([
                'consumed_at' => now(),
                'lightning_public_key' => $publicKey,
            ]);

            return response()->json([
                'status' => 'OK',
            ]);
        }
    }

    /**
     * Complete registration by providing email address.
     * Accepts either user_id (existing LNURL user without verified email) or k1 (new key: no user yet).
     * Email is validated as unique in our DB and on BTCPay before creating/updating any user.
     */
    public function completeRegistration(Request $request)
    {
        if (! config('services.lnurl_auth.enabled', false)) {
            return response()->json(['error' => 'LNURL-auth is not enabled'], 403);
        }

        $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'k1' => ['nullable', 'string', 'size:64'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
        ]);

        $userId = $request->input('user_id');
        $k1 = $request->input('k1');

        if ($userId && $k1) {
            return response()->json(['error' => 'Provide either user_id or k1, not both.'], 422);
        }
        if (! $userId && ! $k1) {
            return response()->json(['error' => 'Provide user_id or k1.'], 422);
        }

        $user = null;
        $publicKey = null;

        if ($userId) {
            $user = User::findOrFail($userId);
            if (! $user->lightning_public_key) {
                return response()->json(['error' => 'Invalid user'], 400);
            }
            $publicKey = $user->lightning_public_key;
        } else {
            $challenge = LnurlAuthChallenge::find($k1);
            if (! $challenge || ! $challenge->isConsumed() || ! $challenge->lightning_public_key) {
                return response()->json(['error' => 'Invalid or expired challenge.'], 400);
            }
            $publicKey = $challenge->lightning_public_key;
        }

        // Validate email uniqueness before creating/updating any user
        try {
            if ($this->userService->checkEmailExists($request->email)) {
                throw ValidationException::withMessages([
                    'email' => ['This email is already registered on BTCPay Server.'],
                ]);
            }
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            Log::warning('BTCPay email check failed during LNURL-auth registration', [
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
            // Reuse unverified user: attach this Lightning key
            $existingUser->update(['lightning_public_key' => $publicKey]);
            if ($user && $user->id !== $existingUser->id) {
                $user->delete();
            }
            $user = $existingUser;
            if ($k1) {
                $challenge = LnurlAuthChallenge::find($k1);
                if ($challenge) {
                    $challenge->update(['pending_user_id' => $existingUser->id]);
                }
            }
        } elseif ($user) {
            $user->update(['email' => $request->email]);
        } else {
            $user = User::create([
                'email' => $request->email,
                'password' => Hash::make(Str::random(32)),
                'lightning_public_key' => $publicKey,
            ]);
            $challenge = LnurlAuthChallenge::find($k1);
            if ($challenge) {
                $challenge->update(['pending_user_id' => $user->id]);
            }
        }

        $verificationUrl = $this->verificationUrl($user);
        try {
            $user->notify(new VerifyEmailNotification($verificationUrl));
        } catch (TransportExceptionInterface $e) {
            Log::warning('Failed to send verification email (LnurlAuth)', [
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

    /**
     * Check if email exists on BTCPay Server.
     */
    public function checkEmailExists(Request $request)
    {
        if (! config('services.lnurl_auth.enabled', false)) {
            return response()->json(['error' => 'LNURL-auth is not enabled'], 403);
        }

        $request->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            $exists = $this->userService->checkEmailExists($request->email);
            
            return response()->json([
                'exists' => $exists,
            ]);
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            Log::error('BTCPay email check failed', [
                'email' => $request->email,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'exists' => false, // Default to false on error
                'error' => 'Could not verify email availability',
            ], 500);
        }
    }

    /**
     * Return whether LNURL-auth is enabled (for frontend; no auth required).
     * No-cache headers so browser always gets current value after .env change.
     */
    public function enabled(): \Illuminate\Http\JsonResponse
    {
        return response()
            ->json(['enabled' => config('services.lnurl_auth.enabled', false)])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Check challenge status (for frontend polling).
     * All responses no-cache so polling always gets fresh state.
     */
    public function challengeStatus(Request $request, string $k1)
    {
        $noCache = function ($response) {
            return $response
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache');
        };

        if (! config('services.lnurl_auth.enabled', false)) {
            return $noCache(response()->json(['error' => 'LNURL-auth is not enabled'], 403));
        }

        $challenge = LnurlAuthChallenge::find($k1);
        if (! $challenge) {
            return $noCache(response()->json([
                'status' => 'error',
                'message' => 'Challenge not found',
            ], 404));
        }

        if ($challenge->isExpired()) {
            return $noCache(response()->json(['status' => 'expired']));
        }

        if (! $challenge->isConsumed()) {
            return $noCache(response()->json(['status' => 'pending']));
        }

        $pendingUserId = $challenge->pending_user_id;
        if ($pendingUserId) {
            $user = User::find($pendingUserId);
            if ($user && ! $user->hasVerifiedEmail()) {
                return $noCache(response()->json([
                    'status' => 'pending_email',
                    'user_id' => $user->id,
                ]));
            }
        }

        // New key: consumed but no user yet; user will be created after email in completeRegistration()
        if ($challenge->lightning_public_key) {
            return $noCache(response()->json([
                'status' => 'pending_email',
                'k1' => $challenge->k1,
            ]));
        }

        if (Auth::check()) {
            return $noCache(response()->json([
                'status' => 'authenticated',
                'user' => Auth::user(),
            ]));
        }

        return $noCache(response()->json(['status' => 'pending']));
    }

    /**
     * Get the verification URL for the given user.
     */
    protected function verificationUrl($user)
    {
        // Ensure we use the correct APP_URL from config
        $baseUrl = rtrim(config('app.url', env('APP_URL', 'http://localhost:8080')), '/');
        
        // Force root URL to ensure correct base URL is used (for email generation)
        URL::forceRootUrl($baseUrl);
        
        // Use Laravel's temporarySignedRoute but manually adjust for API route
        // This ensures proper signature generation
        $route = 'verification.verify';
        
        // Temporarily change route to point to API
        $url = URL::temporarySignedRoute(
            $route,
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );
        
        // Use /auth/verify-email for Vue router (component will call API internally)
        // Replace /api/auth/verify-email back to /auth/verify-email if needed
        $url = str_replace('/api/auth/verify-email/', '/auth/verify-email/', $url);
        
        return $url;
    }

    /**
     * Convert DER-encoded ECDSA signature (hex) to 128-char flat hex (r||s) for kornrunner.
     * DER: 0x30 [totalLen] 0x02 [rLen] [r] 0x02 [sLen] [s]
     */
    protected function derSignatureToFlatHex(string $derHex): string
    {
        $bytes = hex2bin($derHex);
        $pos = 0;
        if ($bytes[$pos++] !== "\x30") {
            throw new \InvalidArgumentException('Invalid DER: expected 0x30');
        }
        $totalLen = ord($bytes[$pos++]);
        if ($pos + $totalLen > strlen($bytes)) {
            throw new \InvalidArgumentException('Invalid DER length');
        }
        // 0x02 rLen r
        if ($bytes[$pos++] !== "\x02") {
            throw new \InvalidArgumentException('Invalid DER: expected 0x02 for r');
        }
        $rLen = ord($bytes[$pos++]);
        $rBytes = substr($bytes, $pos, $rLen);
        $pos += $rLen;
        // 0x02 sLen s
        if ($bytes[$pos++] !== "\x02") {
            throw new \InvalidArgumentException('Invalid DER: expected 0x02 for s');
        }
        $sLen = ord($bytes[$pos++]);
        $sBytes = substr($bytes, $pos, $sLen);

        $r = gmp_init(bin2hex($rBytes), 16);
        $s = gmp_init(bin2hex($sBytes), 16);

        return str_pad(gmp_strval($r, 16), 64, '0', STR_PAD_LEFT)
            . str_pad(gmp_strval($s, 16), 64, '0', STR_PAD_LEFT);
    }
}
