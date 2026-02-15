<?php

namespace App\Http\Controllers;

use App\Models\DocumentationArticle;
use App\Models\DocumentationCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentationController extends Controller
{
    /** Supported locales for documentation (query param overrides session/header). */
    private const SUPPORTED_LOCALES = ['en', 'sk', 'es', 'cz', 'de', 'fr', 'hu', 'pl'];

    private function resolveLocale(Request $request): string
    {
        $locale = $request->query('locale');
        if ($locale && in_array($locale, self::SUPPORTED_LOCALES, true)) {
            return $locale;
        }
        return app()->getLocale();
    }

    /**
     * Get all published documentation articles with optional filtering.
     */
    public function index(Request $request)
    {
        $locale = $this->resolveLocale($request);
        app()->setLocale($locale);

        $categoryId = $request->query('category_id');
        $search = $request->query('search');

        $query = DocumentationArticle::query()
            ->published()
            ->with(['category', 'creator'])
            ->orderBy('order')
            ->orderBy('created_at', 'desc');

        // Filter by category
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        // Search in title and content (current locale)
        if ($search) {
            $query->where(function ($q) use ($search, $locale) {
                $q->whereRaw("title->>'{$locale}' ILIKE ?", ["%{$search}%"])
                  ->orWhereRaw("content->>'{$locale}' ILIKE ?", ["%{$search}%"])
                  // Fallback to English if current locale doesn't have content
                  ->orWhereRaw("title->>'en' ILIKE ?", ["%{$search}%"])
                  ->orWhereRaw("content->>'en' ILIKE ?", ["%{$search}%"]);
            });
        }

        $articles = $query->get()->map(function ($article) use ($locale) {
            return [
                'id' => $article->id,
                'slug' => $article->slug,
                'title' => $article->getLocalizedTitleAttribute(),
                'content' => $article->getLocalizedContentAttribute(),
                'meta_description' => $article->getLocalizedMetaDescriptionAttribute(),
                'category' => $article->category ? [
                    'id' => $article->category->id,
                    'slug' => $article->category->slug,
                    'name' => $article->category->getLocalizedNameAttribute(),
                ] : null,
                'created_at' => $article->created_at,
                'updated_at' => $article->updated_at,
            ];
        });

        // Get categories for filtering
        $categories = DocumentationCategory::active()
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
            'data' => $articles,
            'categories' => $categories,
        ]);
    }

    /**
     * Get a single documentation article by slug.
     */
    public function show(Request $request, string $slug)
    {
        $locale = $this->resolveLocale($request);
        app()->setLocale($locale);

        $article = DocumentationArticle::where('slug', $slug)
            ->published()
            ->with(['category', 'creator', 'updater'])
            ->firstOrFail();

        return response()->json([
            'data' => [
                'id' => $article->id,
                'slug' => $article->slug,
                'title' => $article->getLocalizedTitleAttribute(),
                'content' => $article->getLocalizedContentAttribute(),
                'meta_description' => $article->getLocalizedMetaDescriptionAttribute(),
                'category' => $article->category ? [
                    'id' => $article->category->id,
                    'slug' => $article->category->slug,
                    'name' => $article->category->getLocalizedNameAttribute(),
                ] : null,
                'created_by' => $article->creator ? [
                    'id' => $article->creator->id,
                    'email' => $article->creator->email,
                ] : null,
                'created_at' => $article->created_at,
                'updated_at' => $article->updated_at,
            ],
        ]);
    }
}

