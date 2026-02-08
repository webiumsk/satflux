<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentationCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DocumentationCategoryController extends Controller
{
    /**
     * List all documentation categories.
     */
    public function index(Request $request)
    {
        $categories = DocumentationCategory::query()
            ->withCount('articles')
            ->orderBy('order')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $categories,
        ]);
    }

    /**
     * Get a single documentation category.
     */
    public function show(DocumentationCategory $category)
    {
        $category->loadCount('articles');

        return response()->json([
            'data' => $category,
        ]);
    }

    /**
     * Create a new documentation category.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'slug' => ['nullable', 'string', 'max:255', 'unique:documentation_categories,slug'],
            'name' => ['required', 'array'],
            'description' => ['nullable', 'array'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Generate slug if not provided
        if (empty($validated['slug']) && isset($validated['name']['en'])) {
            $validated['slug'] = Str::slug($validated['name']['en']);
            // Ensure uniqueness
            $originalSlug = $validated['slug'];
            $counter = 1;
            while (DocumentationCategory::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $originalSlug . '-' . $counter;
                $counter++;
            }
        }

        $category = DocumentationCategory::create([
            'slug' => $validated['slug'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'order' => $validated['order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Reload with count
        $category->loadCount('articles');

        return response()->json([
            'data' => $category,
            'message' => __('messages.documentation_category_created'),
        ], 201);
    }

    /**
     * Update a documentation category.
     */
    public function update(Request $request, DocumentationCategory $category)
    {
        $validated = $request->validate([
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('documentation_categories', 'slug')->ignore($category->id),
            ],
            'name' => ['sometimes', 'array'],
            'description' => ['nullable', 'array'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Generate slug if not provided and name changed
        if (empty($validated['slug']) && isset($validated['name']['en']) && $validated['name']['en'] !== ($category->name['en'] ?? '')) {
            $validated['slug'] = Str::slug($validated['name']['en']);
            // Ensure uniqueness
            $originalSlug = $validated['slug'];
            $counter = 1;
            while (DocumentationCategory::where('slug', $validated['slug'])->where('id', '!=', $category->id)->exists()) {
                $validated['slug'] = $originalSlug . '-' . $counter;
                $counter++;
            }
        }

        $category->update($validated);

        // Reload with count
        $category->loadCount('articles');

        return response()->json([
            'data' => $category,
            'message' => __('messages.documentation_category_updated'),
        ]);
    }

    /**
     * Delete a documentation category.
     */
    public function destroy(DocumentationCategory $category)
    {
        // Check if category has articles
        if ($category->articles()->count() > 0) {
            return response()->json([
                'message' => __('messages.documentation_category_has_articles'),
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => __('messages.documentation_category_deleted'),
        ]);
    }
}

