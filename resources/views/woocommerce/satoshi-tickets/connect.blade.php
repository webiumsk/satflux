<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Connect Store - {{ config('app.name', 'satflux.io') }}</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Outfit', sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; }
        .card { background: #1e293b; border-radius: 1rem; padding: 2rem; max-width: 420px; width: 100%; border: 1px solid #334155; }
        h1 { font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem; }
        p { color: #94a3b8; font-size: 0.875rem; margin-bottom: 1.5rem; }
        form { display: flex; flex-direction: column; gap: 1rem; }
        select { background: #0f172a; border: 1px solid #475569; border-radius: 0.5rem; padding: 0.75rem 1rem; color: #e2e8f0; font-size: 1rem; font-family: inherit; }
        button { background: #f59e0b; color: #0f172a; font-weight: 600; padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; font-size: 1rem; cursor: pointer; font-family: inherit; transition: background 0.15s; }
        button:hover { background: #fbbf24; }
        a.back { color: #94a3b8; font-size: 0.875rem; text-decoration: none; margin-top: 1rem; display: inline-block; }
        a.back:hover { color: #e2e8f0; }
        input[type="hidden"] { display: none; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Connect to WooCommerce</h1>
        <p>Select the store to connect with Satoshi Tickets.</p>
        <form method="POST" action="{{ url('/woocommerce/satoshi-tickets/connect/select-store') }}">
            @csrf
            <input type="hidden" name="return_url" value="{{ e($returnUrl) }}">
            <select name="store_id" required>
                <option value="">— Select store —</option>
                @foreach($stores as $store)
                    <option value="{{ $store->id }}">{{ e($store->name) }}</option>
                @endforeach
            </select>
            <button type="submit">Connect</button>
        </form>
        <a href="{{ config('app.url') }}/stores" class="back">← Back to {{ config('app.name', 'satflux.io') }}</a>
    </div>
</body>
</html>
