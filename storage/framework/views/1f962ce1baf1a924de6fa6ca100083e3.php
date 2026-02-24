<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <title><?php echo e(config('app.name', 'satflux.io')); ?> - Accept Bitcoin Without Limits</title>
    <meta name="description" content="Accept Bitcoin & Lightning payments without limits. Non-custodial BTCPay Server control panel with multi-store management, PoS terminals, and advanced analytics.">

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/favicon.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/favicon.png">

    <!-- Canonical -->
    <link rel="canonical" href="<?php echo e(url()->current()); ?>">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo e(url()->current()); ?>">
    <meta property="og:title" content="<?php echo e(config('app.name', 'satflux.io')); ?> - Bitcoin Payment Control Panel">
    <meta property="og:description" content="Accept Bitcoin & Lightning payments without limits. Non-custodial BTCPay Server control panel with multi-store management, PoS terminals, and advanced analytics.">
    <meta property="og:image" content="<?php echo e(config('app.url')); ?>/og-image.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?php echo e(url()->current()); ?>">
    <meta name="twitter:title" content="<?php echo e(config('app.name', 'satflux.io')); ?> - Bitcoin Payment Control Panel">
    <meta name="twitter:description" content="Accept Bitcoin & Lightning payments without limits. Non-custodial BTCPay Server control panel with multi-store management, PoS terminals, and advanced analytics.">
    <meta name="twitter:image" content="<?php echo e(config('app.url')); ?>/og-image.png">

    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.ts']); ?>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Matomo -->
<script>
  var _paq = window._paq = window._paq || [];
  /* tracker methods like "setCustomDimension" should be called before "trackPageView" */
  _paq.push(["setExcludedQueryParams", ["account","accountnum","address","address1","address2","address3","addressline1","addressline2","adres","adresse","age","alter","auth","authpw","bic","billingaddress","billingaddress1","billingaddress2","calle","cardnumber","cc","ccc","cccsc","cccvc","cccvv","ccexpiry","ccexpmonth","ccexpyear","ccname","ccnumber","cctype","cell","cellphone","city","clientid","clientsecret","company","consumerkey","consumersecret","contrasenya","contrase\u00f1a","creditcard","creditcardnumber","cvc","cvv","dateofbirth","debitcard","direcci\u00f3n","dob","domain","ebost","email","emailaddress","emailadresse","epos","epost","eposta","exp","familyname","firma","firstname","formlogin","fullname","gender","geschlecht","gst","gstnumber","handynummer","has\u0142o","heslo","iban","ibanaccountnum","ibanaccountnumber","id","identifier","indirizzo","kartakredytowa","kennwort","keyconsumerkey","keyconsumersecret","konto","kontonr","kontonummer","kredietkaart","kreditkarte","kreditkort","lastname","login","mail","mobiili","mobile","mobilne","nachname","name","nickname","osoite","parole","pass","passord","password","passwort","pasword","paswort","paword","phone","pin","plz","postalcode","postcode","postleitzahl","privatekey","publickey","pw","pwd","pword","pwrd","rue","secret","secretq","secretquestion","shippingaddress","shippingaddress1","shippingaddress2","socialsec","socialsecuritynumber","socsec","sokak","ssn","steuernummer","strasse","street","surname","swift","tax","taxnumber","tel","telefon","telefonnr","telefonnummer","telefono","telephone","token","token_auth","tokenauth","t\u00e9l\u00e9phone","ulica","user","username","vat","vatnumber","via","vorname","wachtwoord","wagwoord","webhooksecret","website","zip","zipcode"]]);
  _paq.push(['trackPageView']);
  _paq.push(['enableLinkTracking']);
  (function() {
    var u="https://stats.dvadsatjeden.org/matomo/";
    _paq.push(['setTrackerUrl', u+'matomo.php']);
    _paq.push(['setSiteId', '6']);
    var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
    g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
  })();
</script>
<!-- End Matomo Code -->
</head>

<body class="font-sans antialiased">
    <?php
        $lnurlAuthEnabled = config('services.lnurl_auth.enabled', false);
    ?>
    <?php if(isset($page)): ?>
        <div id="app" data-page="<?php echo e(is_array($page) ? json_encode($page) : $page); ?>" data-lnurl-auth-enabled="<?php echo e($lnurlAuthEnabled ? 'true' : 'false'); ?>"></div>
    <?php else: ?>
        <div id="app" data-lnurl-auth-enabled="<?php echo e($lnurlAuthEnabled ? 'true' : 'false'); ?>"></div>
    <?php endif; ?>
</body>

</html><?php /**PATH /var/www/resources/views/app.blade.php ENDPATH**/ ?>