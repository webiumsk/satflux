<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosOrder extends Model
{
    use HasFactory;

    protected $table = 'pos_orders';

    protected $fillable = [
        'pos_terminal_id',
        'store_id',
        'amount',
        'currency',
        'status',
        'paid_method',
        'btcpay_invoice_id',
        'metadata_json',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:8',
            'metadata_json' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_EXPIRED = 'expired';

    public const PAID_METHOD_LIGHTNING = 'lightning';
    public const PAID_METHOD_ONCHAIN = 'onchain';
    public const PAID_METHOD_CASH = 'cash';
    public const PAID_METHOD_CARD = 'card';

    public function posTerminal(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class, 'pos_terminal_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function isOfflinePayment(): bool
    {
        return in_array($this->paid_method, [self::PAID_METHOD_CASH, self::PAID_METHOD_CARD], true);
    }
}
