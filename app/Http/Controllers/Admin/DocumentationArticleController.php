<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentationArticleRequest;
use App\Models\DocumentationArticle;
use App\Models\DocumentationCategory;
use Illuminate\Http\Request;

class DocumentationArticleController extends Controller
{
    /**
     * List all documentation articles (admin view - includes unpublished).
     */
    public function index(Request $request)
    {
        $query = DocumentationArticle::query()
            ->with(['category', 'creator', 'updater'])
            ->orderBy('created_at', 'desc');

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by published status
        if ($request->has('is_published')) {
            $query->where('is_published', $request->boolean('is_published'));
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereRaw("title->>'en' ILIKE ?", ["%{$search}%"])
                  ->orWhereRaw("content->>'en' ILIKE ?", ["%{$search}%"]);
            });
        }

        $articles = $query->paginate(20);

        return response()->json([
            'data' => $articles->items(),
            'meta' => [
                'current_page' => $articles->currentPage(),
                'last_page' => $articles->lastPage(),
                'per_page' => $articles->perPage(),
                'total' => $articles->total(),
            ],
        ]);
    }

    /**
     * Get a single documentation article.
     */
    public function show(DocumentationArticle $article)
    {
        $article->load(['category', 'creator', 'updater']);

        return response()->json([
            'data' => $article,
        ]);
    }

    /**
     * Create a new documentation article.
     */
    public function store(DocumentationArticleRequest $request)
    {
        $user = $request->user();

        // Generate slug if not provided (from first available title, prefer en)
        $slug = $request->slug;
        if (empty($slug) && is_array($request->title)) {
            $titleForSlug = $request->title['en'] ?? null;
            if (empty($titleForSlug)) {
                foreach (['sk', 'es', 'cz', 'de', 'fr', 'hu', 'pl'] as $locale) {
                    if (!empty($request->title[$locale])) {
                        $titleForSlug = $request->title[$locale];
                        break;
                    }
                }
            }
            if (!empty($titleForSlug)) {
                $slug = DocumentationArticle::generateSlug($titleForSlug);
            }
        }
        if (empty($slug)) {
            $slug = DocumentationArticle::generateSlug('untitled');
        }

        $article = DocumentationArticle::create([
            'slug' => $slug,
            'title' => $request->title,
            'content' => $request->content,
            'category_id' => $request->category_id,
            'order' => $request->order ?? 0,
            'is_published' => $request->boolean('is_published', false),
            'meta_description' => $request->meta_description,
            'created_by' => $user->id,
        ]);

        $article->load(['category', 'creator']);

        return response()->json([
            'data' => $article,
            'message' => __('messages.documentation_article_created'),
        ], 201);
    }

    /**
     * Update a documentation article.
     */
    public function update(DocumentationArticleRequest $request, DocumentationArticle $article)
    {
        $user = $request->user();

        // Generate slug if not provided and title changed
        $slug = $request->slug ?? $article->slug;
        if (empty($request->slug) && isset($request->title['en']) && $request->title['en'] !== ($article->title['en'] ?? '')) {
            $slug = DocumentationArticle::generateSlug($request->title['en']);
        }

        $article->update([
            'slug' => $slug,
            'title' => $request->title,
            'content' => $request->content,
            'category_id' => $request->category_id,
            'order' => $request->order ?? $article->order,
            'is_published' => $request->boolean('is_published', $article->is_published),
            'meta_description' => $request->meta_description,
            'updated_by' => $user->id,
        ]);

        $article->load(['category', 'creator', 'updater']);

        return response()->json([
            'data' => $article,
            'message' => __('messages.documentation_article_updated'),
        ]);
    }

    /**
     * Delete a documentation article.
     */
    public function destroy(DocumentationArticle $article)
    {
        $article->delete();

        return response()->json([
            'message' => __('messages.documentation_article_deleted'),
        ]);
    }
}

