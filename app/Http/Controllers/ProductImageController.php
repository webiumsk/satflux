<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    /**
     * Upload a product image.
     */
    public function upload(Request $request, \App\Models\Store $store)
    {
        $user = $request->user();

        // Ensure user owns the store
        if ($store->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'image' => 'required|image|max:5120', // Max 5MB
        ]);

        try {
            // Store file in public storage
            $file = $request->file('image');
            $path = $file->store('products', 'public');

            // Generate public URL - use absolute URL for BTCPay compatibility
            $relativeUrl = Storage::disk('public')->url($path);
            
            // Convert to absolute URL if relative
            if (str_starts_with($relativeUrl, '/')) {
                $baseUrl = rtrim(config('app.url', env('APP_URL', 'http://localhost')), '/');
                $url = $baseUrl . $relativeUrl;
            } else {
                $url = $relativeUrl;
            }

            Log::info('Product image uploaded', [
                'store_id' => $store->id,
                'user_id' => $user->id,
                'path' => $path,
                'url' => $url,
                'relative_url' => $relativeUrl,
            ]);

            return response()->json([
                'message' => 'Image uploaded successfully',
                'data' => [
                    'url' => $url,
                    'image_url' => $url, // Alias for compatibility
                    'path' => $path,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to upload product image', [
                'store_id' => $store->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Failed to upload image: ' . $e->getMessage()], 500);
        }
    }
}

