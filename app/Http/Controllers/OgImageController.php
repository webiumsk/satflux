<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class OgImageController extends Controller
{
    /**
     * Serve static OG image for social media sharing.
     * 
     * Returns the pre-designed og-image.webp file from public directory.
     */
    public function generate(Request $request): Response
    {
        $imagePath = public_path('og-image.webp');
        
        if (!file_exists($imagePath)) {
            abort(404, 'OG image not found');
        }

        return response()->file($imagePath, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'public, max-age=86400, immutable',
        ]);
    }
}
