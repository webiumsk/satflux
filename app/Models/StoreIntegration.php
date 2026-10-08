<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreIntegration extends Model
{
    use HasFactory, HasUuids;

    /**
     * Secrets never leave the model through toArray()/JSON; controllers
     * expose them explicitly where intended.
     *
     * @var list<string>
     */
    protected $hidden = ['integration_secret', 'token_hash'];

    protected $fillable = [
        'store_id',
        'company_id',
        'platform',
        'token_hash',
        'integration_secret',
        'webhook_url',
        'is_active',
        'last_used_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'integration_secret' => 'encrypted',
            'metadata' => 'array',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function documentInbox(): HasMany
    {
        return $this->hasMany(IntegrationDocumentInbox::class);
    }

    /**
     * @return array{integration: self, token: string, secret: string}
     */
    public static function createForStore(Store $store, ?string $webhookUrl = null): array
    {
        $token = 'sfwc_'.Str::random(48);
        $secret = Str::random(64);

        $integration = DB::transaction(function () use ($store, $token, $secret, $webhookUrl): self {
            $store = Store::query()->lockForUpdate()->findOrFail($store->id);
            $existing = self::query()->where('store_id', $store->id)->where('platform', 'woocommerce')->first();
            if ($existing && $existing->company_id !== $store->company_id && $existing->documentInbox()->exists()) {
                throw ValidationException::withMessages([
                    'store_id' => ['Import or dismiss existing integration inbox entries before reconnecting this store to a different company.'],
                ]);
            }

            return self::updateOrCreate(
                [
                    'store_id' => $store->id,
                    'platform' => 'woocommerce',
                ],
                [
                    'company_id' => $store->company_id,
                    'token_hash' => hash('sha256', $token),
                    'integration_secret' => $secret,
                    'webhook_url' => $webhookUrl,
                    'is_active' => true,
                    'metadata' => [
                        'created_at' => now()->toIso8601String(),
                    ],
                ]
            );
        });

        return [
            'integration' => $integration,
            'token' => $token,
            'secret' => $secret,
        ];
    }

    public function hasCurrentCompanyLink(): bool
    {
        /** @var Store|null $store */
        $store = $this->store()->first();

        return $store !== null && $this->company_id === $store->company_id
            && self::query()->whereKey($this->id)
                ->where('is_active', true)
                ->where('token_hash', $this->token_hash)
                ->where('company_id', $this->company_id)
                ->exists();
    }

    public static function findByToken(string $token): ?self
    {
        if ($token === '') {
            return null;
        }

        return self::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('is_active', true)
            ->first();
    }
}
