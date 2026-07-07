<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds a Content-Security-Policy header (opt-in via config/security.php).
 * See the config file for the rollout procedure and policy rationale.
 */
class SetSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('security.csp.enabled')) {
            return $response;
        }

        $header = config('security.csp.report_only')
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        if (! $response->headers->has($header)) {
            $response->headers->set($header, $this->buildPolicy());
        }

        return $response;
    }

    protected function buildPolicy(): string
    {
        // Matomo (when configured) loads matomo.js from its own origin.
        $matomoOrigin = $this->originOf((string) config('services.matomo.url', ''));

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => array_filter(["'self'", $matomoOrigin]),
            // 'unsafe-inline' styles: Vue transitions/components set inline style attributes.
            // Google Fonts: WooCommerce connect pages (resources/views/woocommerce/*).
            'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'],
            'font-src' => ["'self'", 'https://fonts.gstatic.com'],
            // https: - user-controlled remote images (crowdfund mainImageUrl, BTCPay logos);
            // blob:/data: - client-generated previews and QR codes.
            'img-src' => ["'self'", 'data:', 'blob:', 'https:'],
            // https:/wss: - Reverb websocket, user-configured Evolu relays, Matomo beacon.
            'connect-src' => ["'self'", 'https:', 'wss:'],
            'worker-src' => ["'self'", 'blob:'],
            // YouTube embeds: landing SK video + documentation articles (both use youtube-nocookie)
            'frame-src' => ["'self'", 'blob:', 'https://www.youtube-nocookie.com', 'https://www.youtube.com'],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'self'"],
        ];

        return collect($directives)
            ->map(fn (array $sources, string $directive) => $directive.' '.implode(' ', $sources))
            ->implode('; ');
    }

    protected function originOf(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        if (! $scheme || ! $host) {
            return null;
        }

        $port = parse_url($url, PHP_URL_PORT);

        return $scheme.'://'.$host.($port ? ':'.$port : '');
    }
}
