<?php

use App\Models\IntegrationDocumentInbox;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_document_inbox', function (Blueprint $table) {
            // One WooCommerce order legitimately carries both a proforma
            // (deferred payment) and its later final invoice - uniqueness
            // must be per document type.
            $table->string('document_type', 32)->default('invoice');
            $table->dropUnique('integration_inbox_wc_order_unique');
            $table->unique(
                ['store_integration_id', 'woocommerce_order_id', 'document_type'],
                'integration_inbox_wc_order_type_unique',
            );
        });

        // Backfill from the payload for existing rows.
        IntegrationDocumentInbox::query()
            ->eachById(function (IntegrationDocumentInbox $entry) {
                $type = (string) (($entry->payload_json['type'] ?? 'invoice') ?: 'invoice');
                if ($type !== 'invoice') {
                    $entry->forceFill(['document_type' => $type])->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        Schema::table('integration_document_inbox', function (Blueprint $table) {
            $table->dropUnique('integration_inbox_wc_order_type_unique');
            $table->unique(
                ['store_integration_id', 'woocommerce_order_id'],
                'integration_inbox_wc_order_unique',
            );
            $table->dropColumn('document_type');
        });
    }
};
