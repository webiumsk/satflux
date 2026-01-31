# Oprava: "Unable to load the app" – HandleInertiaRequests musí byť v web middleware

Inertia vyžaduje, aby **HandleInertiaRequests** bol zaregistrovaný v **globálnom web middleware**, nie len v route skupinách. Inak sa pri prvom načítaní stránky nevygeneruje `data-page` a aplikácia zobrazí chybu.

## Čo urobiť

1. Otvor **`bootstrap/app.php`**.

2. Nájdi blok `->withMiddleware(function (Middleware $middleware) {` a v ňom sekciu s `$middleware->web(...)`.

3. **Pridaj** tieto riadky hneď za `$middleware->web(prepend: [...])` (pred `$middleware->statefulApi();`):

```php
$middleware->web(append: [
    \App\Http\Middleware\HandleInertiaRequests::class,
]);
```

Výsledok by mal vyzerať takto:

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

4. Ulož súbor a obnov stránku v prehliadači (ideálne hard refresh: Ctrl+Shift+R alebo Cmd+Shift+R).

Potom by sa mala aplikácia načítať s Inertia dátami a chyba by mala zmiznúť.
