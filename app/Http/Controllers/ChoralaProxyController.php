<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class ChoralaProxyController extends Controller
{
    /**
     * Same-origin proxy for Chorala public/widget API (avoids browser CORS on localhost).
     * Only /public/* paths are forwarded.
     */
    public function forward(Request $request, string $path): Response
    {
        if (! config('services.chorala.project_key')) {
            abort(404);
        }

        $normalized = ltrim($path, '/');
        if (! str_starts_with($normalized, 'public/')) {
            abort(403, 'Only Chorala public API paths are proxied.');
        }

        $base = rtrim((string) config('services.chorala.widget_url'), '/');
        $url = "{$base}/api/v1/{$normalized}";
        if ($request->getQueryString()) {
            $url .= '?'.$request->getQueryString();
        }

        $headers = array_filter([
            'X-Chorala-Key' => $request->header('X-Chorala-Key') ?: config('services.chorala.project_key'),
            'X-Chorala-User' => $request->header('X-Chorala-User'),
            'Accept' => $request->header('Accept', 'application/json'),
            'Content-Type' => $request->header('Content-Type'),
        ], static fn ($value) => $value !== null && $value !== '');

        $client = Http::withHeaders($headers)->timeout(30);

        $response = match (strtoupper($request->method())) {
            'GET' => $client->get($url),
            'POST' => $client->withBody($request->getContent(), $request->header('Content-Type', 'application/json'))->post($url),
            'DELETE' => $client->delete($url),
            'PATCH' => $client->withBody($request->getContent(), $request->header('Content-Type', 'application/json'))->patch($url),
            default => abort(405),
        };

        return response($response->body(), $response->status())
            ->withHeaders(array_filter([
                'Content-Type' => $response->header('Content-Type'),
            ]));
    }
}
