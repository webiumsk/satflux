<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <title><?php echo e(config('app.name', 'satflux.io')); ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/favicon.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/favicon.png">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo e(config('app.url')); ?>">
    <meta property="og:title" content="<?php echo e(config('app.name', 'satflux.io')); ?> - Bitcoin Payment Control Panel">
    <meta property="og:description" content="Accept Bitcoin & Lightning payments without limits. Non-custodial BTCPay Server control panel with multi-store management, PoS terminals, and advanced analytics.">
    <meta property="og:image" content="<?php echo e(config('app.url')); ?>/og-image.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?php echo e(config('app.url')); ?>">
    <meta name="twitter:title" content="<?php echo e(config('app.name', 'satflux.io')); ?> - Bitcoin Payment Control Panel">
    <meta name="twitter:description" content="Accept Bitcoin & Lightning payments without limits. Non-custodial BTCPay Server control panel with multi-store management, PoS terminals, and advanced analytics.">
    <meta name="twitter:image" content="<?php echo e(config('app.url')); ?>/og-image.png">

    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.ts']); ?>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body class="font-sans antialiased">
    <?php if(isset($page)): ?>
        <div id="app" data-page="<?php echo e($page); ?>"></div>
    <?php else: ?>
        <div id="app"></div>
    <?php endif; ?>
</body>

</html><?php /**PATH /var/www/resources/views/app.blade.php ENDPATH**/ ?>