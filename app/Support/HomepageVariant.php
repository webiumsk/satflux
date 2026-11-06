<?php

namespace App\Support;

final class HomepageVariant
{
    /** Resolve the configured homepage, falling back to legacy for every invalid value. */
    public static function resolve(): string
    {
        return config('marketing.homepage_variant') === 'showcase' ? 'showcase' : 'legacy';
    }
}
