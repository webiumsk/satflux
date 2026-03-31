<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreEmailRuleDispatch extends Model
{
    protected $fillable = [
        'store_email_rule_id',
        'webhook_event_id',
        'dispatch_key',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(StoreEmailRule::class, 'store_email_rule_id');
    }

    public function webhookEvent(): BelongsTo
    {
        return $this->belongsTo(WebhookEvent::class);
    }
}
