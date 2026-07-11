<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_settlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();

            // Payment identity as reported by BTCPay Greenfield invoice payment-methods.
            $table->string('btcpay_invoice_id');
            $table->string('payment_method_id'); // BTC-LN, BTC-CHAIN, BTC-LNURL, ...
            $table->string('payment_id');        // payments[].id (LN payment hash / onchain txid-vout)

            // lightning_boltz | lightning | onchain | other (category derived from method + store wallet type)
            $table->string('category');
            $table->string('destination', 2048)->nullable();
            $table->string('payment_status')->nullable(); // BTCPay payment status (Processing/Settled/...)
            $table->timestamp('paid_at')->nullable();

            $table->bigInteger('gross_sats');
            $table->string('invoice_currency', 16)->nullable();
            $table->decimal('invoice_amount', 20, 8)->nullable();
            $table->decimal('rate', 20, 8)->nullable();

            // Settlement side. Without upstream swap reports (BTCPay Greenfield reports endpoint is
            // [NonAction]-disabled and the Boltz plugin exposes no swap data via API), the net side of
            // Boltz swaps can only ever be estimated - never silently derived. Quality semantics:
            // estimated = computed from the public Boltz pair fee snapshot in estimate_basis
            // derived   = equals another verifiable value (direct on-chain: net == gross)
            // unknown   = backend provides nothing usable
            // reported/final are reserved for a future upstream unlock and unused today.
            $table->string('settlement_asset', 16)->nullable();
            $table->bigInteger('estimated_service_fee_sats')->nullable();
            $table->bigInteger('estimated_network_fee_sats')->nullable();
            $table->bigInteger('estimated_net_settlement_sats')->nullable();
            $table->json('estimate_basis')->nullable();
            $table->string('net_quality', 16)->default('unknown');

            // Reserved for future upstream data (plugin swap reports); always null today.
            $table->string('boltz_swap_id')->nullable();
            $table->string('settlement_txid')->nullable();

            $table->json('flags')->nullable(); // reconciliation flags (stuck, amount_mismatch, ...)
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique(
                ['store_id', 'btcpay_invoice_id', 'payment_method_id', 'payment_id'],
                'store_settlements_payment_identity_unique'
            );
            $table->index(['store_id', 'paid_at']);
            $table->index(['store_id', 'btcpay_invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_settlements');
    }
};
