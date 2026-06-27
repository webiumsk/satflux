<?php

namespace App\Http\Controllers;

use App\Services\Chorala\ChoralaWidgetTokenService;
use Illuminate\Http\Request;

class ChoralaController extends Controller
{
    public function __construct(
        protected ChoralaWidgetTokenService $choralaWidgetTokenService,
    ) {}

    public function widgetToken(Request $request)
    {
        $jwt = $this->choralaWidgetTokenService->createTokenForUser($request->user());

        if ($jwt === null) {
            return response()->json(['message' => 'Chorala widget SSO is not configured.'], 404);
        }

        return response()->json(['jwt' => $jwt]);
    }
}
