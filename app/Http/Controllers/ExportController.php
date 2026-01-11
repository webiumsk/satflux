<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportRequest;
use App\Jobs\GenerateCsvExport;
use App\Models\Export;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    /**
     * List exports for a store.
     */
    public function index(Request $request)
    {
        $store = $request->route('store');

        $exports = Export::where('store_id', $store->id)
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $exports]);
    }

    /**
     * Create a new export.
     */
    public function store(ExportRequest $request)
    {
        $store = $request->route('store');

        $export = Export::create([
            'store_id' => $store->id,
            'user_id' => $request->user()->id,
            'format' => $request->format,
            'filters' => [
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'status' => $request->status,
            ],
        ]);

        // Dispatch job
        GenerateCsvExport::dispatch($export);

        return response()->json([
            'data' => $export,
            'message' => 'Export job queued',
        ], 201);
    }

    /**
     * Get download URL for an export.
     */
    public function download(Request $request, Export $export)
    {
        // Verify ownership
        if ($export->user_id !== $request->user()->id) {
            abort(403);
        }

        if (!$export->isFinished()) {
            return response()->json(['message' => 'Export is not ready yet'], 202);
        }

        if ($export->signed_url && $export->expires_at && $export->expires_at->isFuture()) {
            return response()->json([
                'data' => [
                    'download_url' => $export->signed_url,
                    'expires_at' => $export->expires_at,
                ],
            ]);
        }

        // Regenerate signed URL if expired
        $ttl = (int) env('EXPORT_SIGNED_URL_TTL', 3600);
        $signedUrl = \Illuminate\Support\Facades\Storage::disk('local')->temporaryUrl(
            $export->file_path,
            now()->addSeconds($ttl)
        );

        $export->update([
            'signed_url' => $signedUrl,
            'expires_at' => now()->addSeconds($ttl),
        ]);

        return response()->json([
            'data' => [
                'download_url' => $signedUrl,
                'expires_at' => $export->expires_at,
            ],
        ]);
    }

    /**
     * List all exports for the user.
     */
    public function all(Request $request)
    {
        $exports = Export::where('user_id', $request->user()->id)
            ->with('store')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $exports]);
    }

    /**
     * Retry a failed export.
     */
    public function retry(Request $request, Export $export)
    {
        // Verify ownership
        if ($export->user_id !== $request->user()->id) {
            abort(403);
        }

        if (!$export->hasFailed()) {
            return response()->json(['message' => 'Export is not in failed state'], 400);
        }

        $export->update([
            'status' => 'pending',
            'error_message' => null,
        ]);

        GenerateCsvExport::dispatch($export);

        return response()->json([
            'data' => $export->fresh(),
            'message' => 'Export job requeued',
        ]);
    }
}

