<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_email_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained()->cascadeOnDelete();
            $table->string('trigger', 128);
            $table->text('condition')->nullable();
            $table->text('to_addresses');
            $table->text('cc_addresses')->nullable();
            $table->text('bcc_addresses')->nullable();
            $table->boolean('send_to_buyer')->default(false);
            $table->string('subject');
            $table->text('body');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['store_id', 'trigger']);
        });

        Schema::create('store_email_rule_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('store_email_rule_id')
                ->constrained('store_email_rules')
                ->cascadeOnDelete();
            $table->foreignId('webhook_event_id')
                ->constrained('webhook_events')
                ->cascadeOnDelete();
            /** BTCPay deliveryId when present, else derived from webhook_event id (idempotency across redeliveries). */
            $table->string('dispatch_key', 256);
            $table->timestamps();

            $table->unique(['store_email_rule_id', 'dispatch_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_email_rule_dispatches');
        Schema::dropIfExists('store_email_rules');
    }
};
