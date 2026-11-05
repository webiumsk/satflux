<?php

namespace App\Support;

final class HomepageVariant
{
    public static function resolve(): string
    {
        return config('marketing.homepage_variant') === 'showcase' ? 'showcase' : 'legacy';
    }
}
