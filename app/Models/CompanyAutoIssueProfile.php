<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Opt-in headless invoicing profile for a local-first company (WooCommerce
 * auto-issue): carries the invoice-header snapshot the server needs to
 * render/email documents without the merchant's browser.
 *
 * @property string $id
 * @property string $company_id
 * @property array<string, mixed> $profile_json
 * @property bool $auto_email
 */
class CompanyAutoIssueProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'company_id',
        'profile_json',
        'auto_email',
    ];

    protected function casts(): array
    {
        return [
            'profile_json' => 'array',
            'auto_email' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
