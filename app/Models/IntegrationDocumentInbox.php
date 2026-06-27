<?php

namespace App\Models;

use App\Enums\IntegrationDocumentInboxStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationDocumentInbox extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'integration_document_inbox';

    protected $fillable = [
        'store_integration_id',
        'woocommerce_order_id',
        'evolu_document_id',
        'payload_json',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'status' => IntegrationDocumentInboxStatus::class,
            'woocommerce_order_id' => 'integer',
        ];
    }

    public function storeIntegration(): BelongsTo
    {
        return $this->belongsTo(StoreIntegration::class);
    }

    public function isPending(): bool
    {
        return $this->status === IntegrationDocumentInboxStatus::Pending;
    }
}
