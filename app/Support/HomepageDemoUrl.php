<?php

namespace App\Support;

final class HomepageDemoUrl
{
    public static function resolve(): string
    {
        $url = config('marketing.demo_pos_url');
        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        $parts = parse_url($url);
        if (! is_array($parts)
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])) {
            return '';
        }

        return $url;
    }
}
