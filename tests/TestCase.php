<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        // Set cache driver to array BEFORE bootstrapping to prevent Redis usage
        $_ENV['CACHE_DRIVER'] = 'array';
        putenv('CACHE_DRIVER=array');
        
        // Also disable Redis completely
        $_ENV['REDIS_CLIENT'] = 'array';
        putenv('REDIS_CLIENT=array');
        
        $app = require __DIR__.'/../bootstrap/app.php';
        
        $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();
        
        // After bootstrap, ensure cache uses array
        config(['cache.default' => 'array']);
        config(['cache.stores.array' => ['driver' => 'array']]);
        
        // Override RateLimiter AFTER bootstrap to use array cache
        // This prevents Redis connection attempts during rate limiting
        // Get the existing RateLimiter to preserve configured limiters
        $existingLimiter = $app->make('Illuminate\Cache\RateLimiter');
        
        // Get existing limiters via reflection before replacing
        $reflection = new \ReflectionClass($existingLimiter);
        $limitersProperty = $reflection->getProperty('limiters');
        $limitersProperty->setAccessible(true);
        $existingLimiters = $limitersProperty->getValue($existingLimiter);
        
        // Clear any existing instance
        if ($app->resolved('Illuminate\Cache\RateLimiter')) {
            $app->forgetInstance('Illuminate\Cache\RateLimiter');
        }
        
        // Create new RateLimiter with array cache
        $newLimiter = new CacheRateLimiter(Cache::store('array'));
        
        // Copy over any configured limiters from the old instance
        if (!empty($existingLimiters)) {
            $newLimitersProperty = new \ReflectionClass($newLimiter);
            $newLimitersProp = $newLimitersProperty->getProperty('limiters');
            $newLimitersProp->setAccessible(true);
            $newLimitersProp->setValue($newLimiter, $existingLimiters);
        }
        
        // Bind the new instance
        $app->instance('Illuminate\Cache\RateLimiter', $newLimiter);
        
        return $app;
    }
}







