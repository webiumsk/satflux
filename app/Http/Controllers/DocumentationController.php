<?php

namespace App\Http\Controllers;

use App\Models\DocumentationArticle;
use App\Models\DocumentationCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentationController extends Controller
{
    /**
     * Search in JSON column (locale key is whitelisted).
     * PostgreSQL: ->>'key' ILIKE; SQLite: json_extract(column, '$.key') LIKE.
     */
    private function applyJsonSearch($query, string $localeKey, string $search): void
    {
        $pattern = '%' . $search . '%';
        if (DB::getDriverName() === 'sqlite') {
            $pathLocale = '$.' . $localeKey;
            $pathEn = '$.en';
            $query->where(function ($q) use ($pathLocale, $pathEn, $pattern) {
                $q->whereRaw('json_extract(title, ?) LIKE ?', [$pathLocale, $pattern])
                    ->orWhereRaw('json_extract(content, ?) LIKE ?', [$pathLocale, $pattern])
                    ->orWhereRaw('json_extract(title, ?) LIKE ?', [$pathEn, $pattern])
                    ->orWhereRaw('json_extract(content, ?) LIKE ?', [$pathEn, $pattern]);
            });
        } else {
            $query->where(function ($q) use ($localeKey, $pattern) {
                $q->whereRaw("title->>'{$localeKey}' ILIKE ?", [$pattern])
                    ->orWhereRaw("content->>'{$localeKey}' ILIKE ?", [$pattern])
                    ->orWhereRaw("title->>'en' ILIKE ?", [$pattern])
                    ->orWhereRaw("content->>'en' ILIKE ?", [$pattern]);
            });
        }
    }
    /**
     * Get all published documentation articles with optional filtering.
     */
    public function index(Request $request)
    {
        $locale = app()->getLocale();
        $allowedLocales = config('localization.json_locale_keys', ['en']);
        $localeKey = in_array($locale, $allowedLocales, true) ? $locale : 'en';

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

        // Search in title and content (current locale; locale key is whitelisted)
        if ($search) {
            $this->applyJsonSearch($query, $localeKey, $search);
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
    public function show(string $slug)
    {
        $locale = app()->getLocale();
        
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

