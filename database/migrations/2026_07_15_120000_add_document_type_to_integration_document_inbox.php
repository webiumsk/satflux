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
        // The old schema allows a single row per order: drop non-invoice
        // entries (transient inbox rows) so recreating the narrower unique
        // constraint cannot fail on an order holding both a proforma and
        // its final invoice.
        IntegrationDocumentInbox::query()
            ->where('document_type', '!=', 'invoice')
            ->delete();

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
