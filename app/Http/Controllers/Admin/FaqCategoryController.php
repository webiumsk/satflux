<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FaqCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FaqCategoryController extends Controller
{
    /**
     * List all FAQ categories.
     */
    public function index(Request $request)
    {
        $categories = FaqCategory::query()
            ->withCount('items')
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $categories,
        ]);
    }

    /**
     * Get a single FAQ category.
     */
    public function show(FaqCategory $category)
    {
        $category->loadCount('items');

        return response()->json([
            'data' => $category,
        ]);
    }

    /**
     * Create a new FAQ category.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'slug' => ['nullable', 'string', 'max:255', 'unique:faq_categories,slug'],
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
            while (FaqCategory::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $originalSlug . '-' . $counter;
                $counter++;
            }
        }

        $category = FaqCategory::create([
            'slug' => $validated['slug'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'order' => $validated['order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'data' => $category,
            'message' => __('messages.faq_category_created'),
        ], 201);
    }

    /**
     * Update a FAQ category.
     */
    public function update(Request $request, FaqCategory $category)
    {
        $validated = $request->validate([
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('faq_categories', 'slug')->ignore($category->id),
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
            while (FaqCategory::where('slug', $validated['slug'])->where('id', '!=', $category->id)->exists()) {
                $validated['slug'] = $originalSlug . '-' . $counter;
                $counter++;
            }
        }

        $category->update($validated);

        return response()->json([
            'data' => $category,
            'message' => __('messages.faq_category_updated'),
        ]);
    }

    /**
     * Delete a FAQ category.
     */
    public function destroy(FaqCategory $category)
    {
        // Check if category has items
        if ($category->items()->count() > 0) {
            return response()->json([
                'message' => __('messages.faq_category_has_items'),
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => __('messages.faq_category_deleted'),
        ]);
    }
}

