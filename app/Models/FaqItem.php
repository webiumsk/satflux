<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FaqItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'slug',
        'question',
        'answer',
        'category_id',
        'order',
        'is_published',
        'view_count',
        'helpful_count',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'question' => 'array',
            'answer' => 'array',
            'is_published' => 'boolean',
            'order' => 'integer',
            'view_count' => 'integer',
            'helpful_count' => 'integer',
        ];
    }

    /**
     * Get the category that owns the FAQ item.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FaqCategory::class, 'category_id');
    }

    /**
     * Get the user who created the FAQ item.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the FAQ item.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope a query to only include published items.
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
     * Increment view count.
     */
    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    /**
     * Increment helpful count.
     */
    public function incrementHelpfulCount(): void
    {
        $this->increment('helpful_count');
    }

    /**
     * Get localized question for current locale.
     */
    public function getLocalizedQuestionAttribute(): ?string
    {
        $locale = app()->getLocale();
        $question = $this->question;
        
        if (is_array($question) && isset($question[$locale])) {
            return $question[$locale];
        }
        
        // Fallback to English
        if (is_array($question) && isset($question['en'])) {
            return $question['en'];
        }
        
        return null;
    }

    /**
     * Get localized answer for current locale.
     */
    public function getLocalizedAnswerAttribute(): ?string
    {
        $locale = app()->getLocale();
        $answer = $this->answer;
        
        if (is_array($answer) && isset($answer[$locale])) {
            return $answer[$locale];
        }
        
        // Fallback to English
        if (is_array($answer) && isset($answer['en'])) {
            return $answer['en'];
        }
        
        return null;
    }

    /**
     * Generate slug from question (English fallback).
     */
    public static function generateSlug(string $question): string
    {
        $slug = Str::slug($question);
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}

