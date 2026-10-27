<?php

namespace App\Providers;

use App\Services\ApiDocumentation\DocumentationRoutes;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\ServiceProvider;

class ApiDocumentationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scramble is a build-time development dependency, absent in production.
        if (class_exists(Scramble::class)) {
            Scramble::ignoreDefaultRoutes();
        }
    }

    public function boot(): void
    {
        if (class_exists(Scramble::class)) {
            Scramble::routes(fn (Route $route): bool => DocumentationRoutes::includes($route));
            Scramble::throwOnError();
        }
    }
}
