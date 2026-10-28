<?php

$configuredHostnames = (string) env('TURNSTILE_HOSTNAMES', '');
$defaultHostname = parse_url((string) config('app.url', ''), PHP_URL_HOST);

return [
    'enabled' => filter_var(env('TURNSTILE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'site_key' => (string) env('TURNSTILE_SITE_KEY', ''),
    'secret_key' => (string) env('TURNSTILE_SECRET_KEY', ''),
    // Use a deployment-controlled hostname, never the request's Host header.
    'hostnames' => $configuredHostnames !== ''
        ? array_values(array_filter(array_map('trim', explode(',', $configuredHostnames))))
        : (is_string($defaultHostname) && $defaultHostname !== '' ? [$defaultHostname] : []),
];
