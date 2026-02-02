<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Shared with NWC Connector service (same PostgreSQL).
     */
    public function up(): void
    {
        Schema::create('nwc_connectors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('store_id');
            $table->string('btcpay_store_id');
            $table->string('nostr_pubkey', 64);
            $table->text('nostr_secret_encrypted');
            $table->string('relay_url')->default('wss://relay.getalby.com/v1');
            $table->enum('backend_type', ['lnd', 'nwc'])->default('lnd');
            $table->text('backend_config_encrypted')->nullable();
            $table->jsonb('allowed_methods')->default('["make_invoice","lookup_invoice"]');
            $table->unsignedInteger('rate_limit_per_min')->default(60);
            $table->enum('status', ['active', 'revoked', 'rotating'])->default('active');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->unique('store_id');
            $table->index('nostr_pubkey');
            $table->index('status');
        });

        Schema::create('nwc_connector_rotations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('connector_id');
            $table->string('old_pubkey', 64);
            $table->string('new_pubkey', 64);
            $table->timestamp('rotated_at');

            $table->foreign('connector_id')->references('id')->on('nwc_connectors')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nwc_connector_rotations');
        Schema::dropIfExists('nwc_connectors');
    }
};
