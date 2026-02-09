<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;

class ReportSettingsController extends Controller
{
    /**
     * Get report settings for a store.
     */
    public function show(Request $request, Store $store)
    {
        return response()->json([
            'data' => [
                'auto_report_enabled' => (bool) $store->auto_report_enabled,
                'auto_report_email' => $store->auto_report_email,
                'auto_report_format' => $store->auto_report_format ?? 'standard',
            ],
            'user_email' => $request->user()->email,
        ]);
    }

    /**
     * Update report settings for a store.
     */
    public function update(Request $request, Store $store)
    {
        $validated = $request->validate([
            'auto_report_enabled' => 'required|boolean',
            'auto_report_email' => 'nullable|email',
            'auto_report_format' => 'required|in:standard,xlsx',
        ]);

        $email = $validated['auto_report_enabled']
            ? ($validated['auto_report_email'] ?: $request->user()->email)
            : null;

        if ($validated['auto_report_enabled'] && !$email) {
            return response()->json([
                'message' => 'Email address is required when automatic reports are enabled.',
            ], 422);
        }

        $store->update([
            'auto_report_enabled' => $validated['auto_report_enabled'],
            'auto_report_email' => $email,
            'auto_report_format' => $validated['auto_report_format'],
        ]);

        return response()->json([
            'data' => [
                'auto_report_enabled' => (bool) $store->auto_report_enabled,
                'auto_report_email' => $store->auto_report_email,
                'auto_report_format' => $store->auto_report_format,
            ],
            'message' => 'Report settings updated',
        ]);
    }
}
