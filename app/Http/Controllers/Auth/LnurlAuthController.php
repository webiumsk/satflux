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
        if (! env('LNURL_AUTH_ENABLED', false)) {
            return response()->json(['error' => 'LNURL-auth is not enabled'], 403);
        }

        $k1 = bin2hex(random_bytes(32));
        $domain = rtrim(env('LNURL_AUTH_DOMAIN', config('app.url')), '/');
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
        if (! env('LNURL_AUTH_ENABLED', false)) {
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

            $domain = rtrim(env('LNURL_AUTH_DOMAIN', config('app.url')), '/');
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
            // User doesn't exist - create new user without email
            $user = User::create([
                'email' => null, // Will be set later
                'password' => Hash::make(Str::random(32)), // Random password, not used for LNURL-auth
                'lightning_public_key' => $publicKey,
            ]);

            // Store user_id in challenge for email completion step
            $challenge->update([
                'consumed_at' => now(),
                'pending_user_id' => $user->id,
            ]);

            return response()->json([
                'status' => 'OK',
            ]);
        }
    }

    /**
     * Complete registration by providing email address.
     */
    public function completeRegistration(Request $request)
    {
        if (! env('LNURL_AUTH_ENABLED', false)) {
            return response()->json(['error' => 'LNURL-auth is not enabled'], 403);
        }

        $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
        ]);

        $user = User::findOrFail($request->user_id);

        // Verify user has lightning_public_key (is LNURL-auth user)
        if (! $user->lightning_public_key) {
            return response()->json(['error' => 'Invalid user'], 400);
        }

        // Check if email already exists on BTCPay Server
        try {
            $emailExists = $this->userService->checkEmailExists($request->email);
            
            if ($emailExists) {
                throw ValidationException::withMessages([
                    'email' => ['This email is already registered on BTCPay Server.'],
                ]);
            }
        } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
            Log::warning('BTCPay email check failed during LNURL-auth registration', [
                'user_id' => $user->id,
                'email' => $request->email,
                'error' => $e->getMessage(),
            ]);
            // Continue even if check fails - we'll try to create BTCPay user anyway
        }

        // Check if email already exists in local database (unverified user)
        $existingUser = User::where('email', $request->email)->first();
        
        if ($existingUser && $existingUser->id !== $user->id) {
            if ($existingUser->hasVerifiedEmail()) {
                throw ValidationException::withMessages([
                    'email' => ['The email has already been taken.'],
                ]);
            }
            
            // If unverified user exists, reuse that account
            // Transfer lightning_public_key to existing user
            $existingUser->update([
                'lightning_public_key' => $user->lightning_public_key,
            ]);
            
            // Delete the new user we just created
            $user->delete();
            $user = $existingUser;
        } else {
            // Update user with email
            $user->update([
                'email' => $request->email,
            ]);
        }

        // Send verification email
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
        if (! env('LNURL_AUTH_ENABLED', false)) {
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
     * Check challenge status (for frontend polling).
     */
    public function challengeStatus(Request $request, string $k1)
    {
        if (! env('LNURL_AUTH_ENABLED', false)) {
            return response()->json(['error' => 'LNURL-auth is not enabled'], 403);
        }

        $challenge = LnurlAuthChallenge::find($k1);
        
        if (! $challenge) {
            return response()->json([
                'status' => 'error',
                'message' => 'Challenge not found',
            ], 404);
        }

        // Check if challenge is expired
        if ($challenge->isExpired()) {
            return response()->json([
                'status' => 'expired',
            ]);
        }

        // Check if challenge has been consumed
        if (! $challenge->isConsumed()) {
            return response()->json([
                'status' => 'pending',
            ]);
        }

        // Challenge is consumed - check if user needs email
        if ($challenge->pending_user_id) {
            $user = User::find($challenge->pending_user_id);
            
            if ($user && ! $user->hasVerifiedEmail()) {
                return response()->json([
                    'status' => 'pending_email',
                    'user_id' => $user->id,
                ]);
            }
        }

        // Check if user is authenticated
        if (Auth::check()) {
            return response()->json([
                'status' => 'authenticated',
                'user' => Auth::user(),
            ]);
        }

        // Challenge consumed but user not authenticated (shouldn't happen normally)
        return response()->json([
            'status' => 'pending',
        ]);
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
