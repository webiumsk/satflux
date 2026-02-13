<?php

namespace App\Http\Controllers;

use App\Models\FaqItem;
use App\Models\FaqCategory;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    /**
     * Get all published FAQ items with optional filtering.
     */
    public function index(Request $request)
    {
        $locale = app()->getLocale();
        $categoryId = $request->query('category_id');
        $search = $request->query('search');

        $query = FaqItem::query()
            ->published()
            ->with(['category'])
            ->orderBy('order')
            ->orderBy('created_at', 'desc');

        // Filter by category
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        // Search in question and answer (current locale)
        if ($search) {
            $query->where(function ($q) use ($search, $locale) {
                $q->whereRaw("question->>'{$locale}' ILIKE ?", ["%{$search}%"])
                  ->orWhereRaw("answer->>'{$locale}' ILIKE ?", ["%{$search}%"])
                  // Fallback to English if current locale doesn't have content
                  ->orWhereRaw("question->>'en' ILIKE ?", ["%{$search}%"])
                  ->orWhereRaw("answer->>'en' ILIKE ?", ["%{$search}%"]);
            });
        }

        $items = $query->get()->map(function ($item) use ($locale) {
            return [
                'id' => $item->id,
                'slug' => $item->slug,
                'question' => $item->getLocalizedQuestionAttribute(),
                'answer' => $item->getLocalizedAnswerAttribute(),
                'category' => $item->category ? [
                    'id' => $item->category->id,
                    'slug' => $item->category->slug,
                    'name' => $item->category->getLocalizedNameAttribute(),
                ] : null,
                'view_count' => $item->view_count,
                'helpful_count' => $item->helpful_count,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ];
        });

        // Get categories for filtering
        $categories = FaqCategory::active()
            ->orderBy('order')
            ->get()
            ->map(function ($category) use ($locale) {
                return [
                    'id' => $category->id,
                    'slug' => $category->slug,
                    'name' => $category->getLocalizedNameAttribute(),
                    'description' => $category->getLocalizedDescriptionAttribute(),
                ];
            });

        return response()->json([
            'data' => $items,
            'categories' => $categories,
        ]);
    }

    /**
     * Get a single FAQ item by slug.
     */
    public function show(string $slug)
    {
        $locale = app()->getLocale();
        
        $item = FaqItem::where('slug', $slug)
            ->published()
            ->with(['category'])
            ->firstOrFail();

        // Increment view count
        $item->incrementViewCount();

        return response()->json([
            'data' => [
                'id' => $item->id,
                'slug' => $item->slug,
                'question' => $item->getLocalizedQuestionAttribute(),
                'answer' => $item->getLocalizedAnswerAttribute(),
                'category' => $item->category ? [
                    'id' => $item->category->id,
                    'slug' => $item->category->slug,
                    'name' => $item->category->getLocalizedNameAttribute(),
                ] : null,
                'view_count' => $item->view_count,
                'helpful_count' => $item->helpful_count,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ],
        ]);
    }

    /**
     * Mark FAQ item as helpful.
     */
    public function markHelpful(string $slug)
    {
        $item = FaqItem::where('slug', $slug)
            ->published()
            ->firstOrFail();

        $item->incrementHelpfulCount();

        return response()->json([
            'message' => __('messages.faq_marked_helpful'),
            'helpful_count' => $item->helpful_count,
        ]);
    }
}

