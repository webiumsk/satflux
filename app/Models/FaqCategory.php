<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FaqCategory extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'order',
        'is_active',
    ];

    protected $hidden = ['content_sync_key', 'content_sync_hash'];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'is_active' => 'boolean',
            'order' => 'integer',
        ];
    }

    /**
     * Get the FAQ items for this category.
     */
    public function items(): HasMany
    {
        return $this->hasMany(FaqItem::class, 'category_id');
    }

    /**
     * Get the published FAQ items for this category.
     */
    public function publishedItems(): HasMany
    {
        return $this->items()->where('is_published', true);
    }

    /**
     * Scope a query to only include active categories.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get localized name for current locale.
     */
    public function getLocalizedNameAttribute(): ?string
    {
        $locale = app()->getLocale();
        $name = $this->getAttribute('name');

        if (is_array($name) && is_string($name[$locale] ?? null) && trim($name[$locale]) !== '') {
            return $name[$locale];
        }

        if ($locale === 'cs' && is_array($name) && is_string($name['cz'] ?? null) && trim($name['cz']) !== '') {
            return $name['cz'];
        }

        // Fallback to English
        if (is_array($name) && isset($name['en'])) {
            return $name['en'];
        }

        return null;
    }

    /**
     * Get localized description for current locale.
     */
    public function getLocalizedDescriptionAttribute(): ?string
    {
        $locale = app()->getLocale();
        $description = $this->getAttribute('description');

        if (is_array($description) && is_string($description[$locale] ?? null) && trim($description[$locale]) !== '') {
            return $description[$locale];
        }

        if ($locale === 'cs' && is_array($description) && is_string($description['cz'] ?? null) && trim($description['cz']) !== '') {
            return $description['cz'];
        }

        // Fallback to English
        if (is_array($description) && isset($description['en'])) {
            return $description['en'];
        }

        return null;
    }
}
