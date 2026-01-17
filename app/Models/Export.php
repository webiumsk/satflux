<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Export extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'store_id',
        'user_id',
        'format',
        'status',
        'file_path',
        'filters',
        'signed_url',
        'expires_at',
        'error_message',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Get the store that owns the export.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the user that created the export.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the export is finished.
     */
    public function isFinished(): bool
    {
        return $this->status === 'finished';
    }

    /**
     * Check if the export has failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the export is running.
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Mark the export as running.
     */
    public function markAsRunning(): void
    {
        $this->update(['status' => 'running']);
    }

    /**
     * Mark the export as finished.
     */
    public function markAsFinished(string $filePath, ?string $signedUrl = null, ?\DateTime $expiresAt = null): void
    {
        $this->update([
            'status' => 'finished',
            'file_path' => $filePath,
            'signed_url' => $signedUrl,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Mark the export as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }
}








