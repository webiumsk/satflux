<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DocumentationArticle extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'slug',
        'title',
        'content',
        'category_id',
        'order',
        'is_published',
        'meta_description',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'content' => 'array',
            'meta_description' => 'array',
            'is_published' => 'boolean',
            'order' => 'integer',
        ];
    }

    /**
     * Get the category that owns the article.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentationCategory::class, 'category_id');
    }

    /**
     * Get the user who created the article.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the article.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope a query to only include published articles.
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope a query to filter by category.
     */
    public function scopeInCategory($query, ?string $categoryId)
    {
        if ($categoryId) {
            return $query->where('category_id', $categoryId);
        }
        return $query;
    }

    /**
     * Get localized title for current locale.
     */
    public function getLocalizedTitleAttribute(): ?string
    {
        $locale = app()->getLocale();
        $title = $this->title;
        
        if (is_array($title) && isset($title[$locale])) {
            return $title[$locale];
        }
        
        // Fallback to English
        if (is_array($title) && isset($title['en'])) {
            return $title['en'];
        }
        
        return null;
    }

    /**
     * Get localized content for current locale.
     */
    public function getLocalizedContentAttribute(): ?string
    {
        $locale = app()->getLocale();
        $content = $this->content;
        
        if (is_array($content) && isset($content[$locale])) {
            return $content[$locale];
        }
        
        // Fallback to English
        if (is_array($content) && isset($content['en'])) {
            return $content['en'];
        }
        
        return null;
    }

    /**
     * Get localized meta description for current locale.
     */
    public function getLocalizedMetaDescriptionAttribute(): ?string
    {
        $locale = app()->getLocale();
        $meta = $this->meta_description;
        
        if (is_array($meta) && isset($meta[$locale])) {
            return $meta[$locale];
        }
        
        // Fallback to English
        if (is_array($meta) && isset($meta['en'])) {
            return $meta['en'];
        }
        
        return null;
    }

    /**
     * Generate slug from title (English fallback).
     */
    public static function generateSlug(string $title): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}

