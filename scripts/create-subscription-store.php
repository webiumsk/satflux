<?php

/**
 * Script to create subscription store record in database
 * 
 * Usage: php scripts/create-subscription-store.php
 * 
 * This creates a Store record for the subscription BTCPay store
 * that already exists on BTCPay Server.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Store;
use App\Models\User;

echo "Creating subscription store record...\n\n";

// BTCPay Store ID (from your BTCPay Server)
$btcpayStoreId = 'GVQwmBoEfPpYY4j7YysmVDbTKmFp24XsFvUZATANVqAY';

// Check if store already exists
$existingStore = Store::where('btcpay_store_id', $btcpayStoreId)->first();

if ($existingStore) {
    echo "✓ Store already exists!\n";
    echo "UUID: {$existingStore->id}\n";
    echo "Name: {$existingStore->name}\n";
    echo "BTCPay Store ID: {$existingStore->btcpay_store_id}\n";
    echo "\nAdd this to your .env file:\n";
    echo "SUBSCRIPTION_STORE_UUID={$existingStore->id}\n";
    exit(0);
}

// Get user (use first user or admin user)
$user = User::first();

if (!$user) {
    echo "❌ Error: No users found in database. Please create a user first.\n";
    exit(1);
}

echo "Using user: {$user->email} (ID: {$user->id})\n\n";

// Create store record
// Use only minimal required fields - let database defaults handle the rest
try {
    $storeData = [
        'user_id' => $user->id,
        'btcpay_store_id' => $btcpayStoreId,
        'name' => 'Subscription Store',
    ];
    
    // Only add optional fields if they exist in the schema
    $columns = \Illuminate\Support\Facades\Schema::getColumnListing('stores');
    if (in_array('default_currency', $columns)) {
        $storeData['default_currency'] = 'EUR';
    }
    if (in_array('timezone', $columns)) {
        $storeData['timezone'] = 'UTC';
    }
    
    $store = Store::create($storeData);

    echo "✓ Store created successfully!\n\n";
    echo "UUID: {$store->id}\n";
    echo "Name: {$store->name}\n";
    echo "BTCPay Store ID: {$store->btcpay_store_id}\n";
    echo "\n📝 Add this to your .env file:\n";
    echo "SUBSCRIPTION_STORE_UUID={$store->id}\n";
    echo "\nAnd also add:\n";
    echo "SUBSCRIPTION_OFFERING_ID=offering_GpWCnNRm6W9qqmgwdC\n";
    echo "SUBSCRIPTION_PLAN_PRO_ID=plan_9UQMqk4vbAFyQinRpL\n";
    
} catch (\Exception $e) {
    echo "❌ Error creating store: {$e->getMessage()}\n";
    exit(1);
}

