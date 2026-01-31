<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DocumentationImageController extends Controller
{
    /**
     * Upload an image for documentation article content.
     * Only authenticated users with support or admin role can upload.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,gif,webp|max:2048', // Max 2MB
        ]);

        try {
            $file = $request->file('image');
            $path = $file->store('documentation', 'public');

            // Return relative URL so images load from the same origin as the frontend
            $fullUrl = Storage::disk('public')->url($path);
            $pathOnly = parse_url($fullUrl, PHP_URL_PATH);
            $url = $pathOnly ? ('/' . ltrim($pathOnly, '/')) : '/storage/' . $path;

            Log::info('Documentation image uploaded', [
                'user_id' => $request->user()?->id,
                'path' => $path,
            ]);

            return response()->json([
                'message' => 'Image uploaded successfully',
                'data' => [
                    'url' => $url,
                    'path' => $path,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to upload documentation image', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Failed to upload image: ' . $e->getMessage()], 500);
        }
    }
}
