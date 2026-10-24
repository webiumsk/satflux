<?php

namespace App\Http\Controllers;

use App\Services\Chorala\ChoralaWidgetSettingsService;
use App\Services\Chorala\ChoralaWidgetTokenService;
use Illuminate\Http\Request;

class ChoralaController extends Controller
{
    public function __construct(
        protected ChoralaWidgetTokenService $choralaWidgetTokenService,
        protected ChoralaWidgetSettingsService $choralaWidgetSettingsService,
    ) {}

    public function widgetToken(Request $request)
    {
        $issuedAt = time();
        $jwt = $this->choralaWidgetTokenService->createTokenForUser($request->user(), $issuedAt);

        if ($jwt === null) {
            return response()->json(['message' => 'Chorala widget SSO is not configured.'], 404);
        }

        return response()->json([
            'jwt' => $jwt,
            'expires_at' => $issuedAt + ChoralaWidgetTokenService::TOKEN_TTL_SECONDS,
        ])->header('Cache-Control', 'no-store');
    }

    public function widgetSettings()
    {
        $settings = $this->choralaWidgetSettingsService->getWidgetSettings();

        if ($settings === null) {
            return response()->json(['message' => 'Chorala widget is not configured.'], 404);
        }

        return response()->json(['settings' => $settings]);
    }
}
