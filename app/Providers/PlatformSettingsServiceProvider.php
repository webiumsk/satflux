<?php

namespace App\Providers;

use App\Services\PlatformSettingsRepository;
use Illuminate\Support\ServiceProvider;

class PlatformSettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PlatformSettingsRepository::class);
    }

    public function boot(): void
    {
        try {
            $this->app->make(PlatformSettingsRepository::class)->applyToConfig();
        } catch (\Throwable) {
            // DB may be unavailable during migrate or early boot.
        }
    }
}
