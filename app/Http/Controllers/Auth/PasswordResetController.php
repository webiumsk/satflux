<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PasswordResetController extends Controller
{
    /** Retired endpoints remain explicit for older clients without sending mail. */
    public function sendResetLink(): JsonResponse
    {
        return $this->unavailable();
    }

    /** Old reset tokens must not change credentials or authenticated sessions. */
    public function reset(): JsonResponse
    {
        return $this->unavailable();
    }

    private function unavailable(): JsonResponse
    {
        return response()->json([
            'code' => 'password_reset_unavailable',
            'message' => __('messages.password_reset_unavailable'),
        ], 410);
    }
}
