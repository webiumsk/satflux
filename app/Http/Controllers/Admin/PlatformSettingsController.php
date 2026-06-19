<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePlatformSettingsRequest;
use App\Models\AuditLog;
use App\Services\PlatformSettingsRepository;
use Illuminate\Http\JsonResponse;

class PlatformSettingsController extends Controller
{
    public function show(PlatformSettingsRepository $repository): JsonResponse
    {
        return response()->json([
            'data' => $repository->adminPayload(),
        ]);
    }

    public function update(
        UpdatePlatformSettingsRequest $request,
        PlatformSettingsRepository $repository,
    ): JsonResponse {
        $repository->updateMany($request->validatedSettings(), $request->user());

        AuditLog::log('platform_settings.updated', 'platform_settings', null);

        return response()->json([
            'data' => $repository->adminPayload(),
        ]);
    }
}
