# Manual change required: bootstrap/app.php

Register Inertia middleware by adding the following **after** the existing `$middleware->web(prepend: [...])` block and **before** `$middleware->statefulApi();`:

```php
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
```

So the full `withMiddleware` callback should look like:

```php
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \App\Http\Middleware\TrustProxies::class,
        ]);
        $middleware->web(prepend: [
            \App\Http\Middleware\TrustProxies::class,
            \App\Http\Middleware\SetLocale::class,
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
        $middleware->statefulApi();
    })
```

Then run: `composer require inertiajs/inertia-laravel` (or `composer update`) if you have not already.
