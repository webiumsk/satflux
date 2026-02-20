<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Services\BtcPay\UserService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class RegisterController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Handle an incoming registration request.
     */
    public function register(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        return DB::transaction(function () use ($request) {
            // Check if email already exists on BTCPay Server
            try {
                $btcpayUser = $this->userService->getUserByEmail($request->email);
                if ($btcpayUser) {
                    throw ValidationException::withMessages([
                        'email' => ['This email is already registered on BTCPay Server. Please use login instead.'],
                    ]);
                }
            } catch (\App\Services\BtcPay\Exceptions\BtcPayException $e) {
                // Log but continue - if BTCPay API fails, we still allow registration
                // User can be synced later
                Log::warning('BTCPay email check failed during registration', [
                    'email' => $request->email,
                    'error' => $e->getMessage(),
                ]);
            }

            // Check if user already exists (unverified)
            $existingUser = User::where('email', $request->email)->first();

            if ($existingUser) {
                // If user exists and is verified, return error
                if ($existingUser->hasVerifiedEmail()) {
                    throw ValidationException::withMessages([
                        'email' => ['The email has already been taken.'],
                    ]);
                }

                // If user exists but is unverified, reuse the account and send new verification email
                $user = $existingUser;
                // Update password in case it changed
                $user->update([
                    'password' => Hash::make($request->password),
                ]);
            } else {
                // Create new Laravel user
                $user = User::create([
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                ]);
            }

            // Send verification email
            // BTCPay user will be created with random password during email verification
            // Merchant never needs BTCPay UI access, so password is not needed
            $verificationUrl = $this->verificationUrl($user);
            try {
                $user->notify(new VerifyEmailNotification($verificationUrl));
            } catch (TransportExceptionInterface $e) {
                Log::warning('Failed to send verification email during registration', [
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
                throw ValidationException::withMessages([
                    'email' => [__('messages.verification_email_failed')],
                ]);
            }

            event(new Registered($user));

            // Don't login user until email is verified
            // User will be logged in after email verification

            return response()->json([
                'message' => __('messages.registration_successful'),
                'user' => $user->fresh(),
            ], 201);
        });
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
        
        // Generate signed URL for /auth/verify-email (Vue router route)
        // The Vue component will then call the API endpoint internally
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );
        
        // But we need to replace the route name 'verification.verify' which points to API
        // So we need to manually construct the URL with /auth/ prefix for Vue router
        // and the API call will be made from the component
        $url = str_replace('/api/auth/verify-email/', '/auth/verify-email/', $url);
        
        return $url;
    }
}




