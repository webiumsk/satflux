<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LnurlAuthChallenge;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LnurlAuthController extends Controller
{
    /**
     * Generate a LNURL-auth challenge.
     */
    public function challenge(Request $request)
    {
        if (! config('app.lnurl_auth_enabled', false)) {
            return response()->json(['error' => 'LNURL-auth is not enabled'], 403);
        }

        $k1 = bin2hex(random_bytes(32));
        $domain = config('app.lnurl_auth_domain', config('app.url'));

        LnurlAuthChallenge::create([
            'k1' => $k1,
            'expires_at' => now()->addMinutes(5),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $lnurlAuthUrl = "{$domain}/api/lnurl-auth/verify?k1={$k1}";

        return response()->json([
            'k1' => $k1,
            'lnurl' => $lnurlAuthUrl,
            'qr' => $lnurlAuthUrl, // QR code data URL can be generated on frontend
        ]);
    }

    /**
     * Verify LNURL-auth signature.
     * 
     * TODO: Implement signature verification logic behind feature flag
     */
    public function verify(Request $request)
    {
        if (! config('app.lnurl_auth_enabled', false)) {
            return response()->json(['error' => 'LNURL-auth is not enabled'], 403);
        }

        $request->validate([
            'k1' => ['required', 'string'],
            'sig' => ['required', 'string'],
            'key' => ['required', 'string'],
        ]);

        // TODO: Implement signature verification
        // This is a skeleton endpoint
        
        return response()->json(['message' => 'Verification endpoint - TODO'], 501);
    }
}

