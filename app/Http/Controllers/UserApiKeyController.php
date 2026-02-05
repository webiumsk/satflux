<?php

namespace App\Http\Controllers;

use App\Models\UserApiKey;
use Illuminate\Http\Request;

/**
 * Panel API keys (for our panel API), not BTCPay store keys.
 */
class UserApiKeyController extends Controller
{
    /**
     * List current user's API keys (plain token never returned).
     */
    public function index(Request $request)
    {
        $keys = $request->user()
            ->userApiKeys()
            ->whereNull('revoked_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (UserApiKey $k) => [
                'id' => $k->id,
                'name' => $k->name,
                'last_used_at' => $k->last_used_at?->toIso8601String(),
                'created_at' => $k->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $keys]);
    }

    /**
     * Create a new API key. Returns plain_token only once.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $result = UserApiKey::createKey($request->user(), $request->input('name'));

        return response()->json([
            'data' => [
                'id' => $result['id'],
                'name' => $result['name'],
                'plain_token' => $result['plain_token'],
                'created_at' => $result['created_at']->toIso8601String(),
            ],
            'message' => 'API key created. Copy the token now; it will not be shown again.',
        ], 201);
    }

    /**
     * Revoke an API key.
     */
    public function destroy(Request $request, UserApiKey $userApiKey)
    {
        if ($userApiKey->user_id !== $request->user()->id) {
            abort(403);
        }

        $userApiKey->update(['revoked_at' => now()]);
        return response()->json(['message' => 'API key revoked'], 204);
    }
}
