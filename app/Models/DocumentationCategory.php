<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentationCategory extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'order',
        'is_active',
    ];

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
     * Get the articles for this category.
     */
    public function articles(): HasMany
    {
        return $this->hasMany(DocumentationArticle::class, 'category_id');
    }

    /**
     * Get the published articles for this category.
     */
    public function publishedArticles(): HasMany
    {
        return $this->articles()->where('is_published', true);
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
        $name = $this->name;
        
        if (is_array($name) && isset($name[$locale])) {
            return $name[$locale];
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
        $description = $this->description;
        
        if (is_array($description) && isset($description[$locale])) {
            return $description[$locale];
        }
        
        // Fallback to English
        if (is_array($description) && isset($description['en'])) {
            return $description['en'];
        }
        
        return null;
    }
}

