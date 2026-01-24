<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\FaqItemRequest;
use App\Models\FaqItem;
use Illuminate\Http\Request;

class FaqItemController extends Controller
{
    /**
     * List all FAQ items (admin view - includes unpublished).
     */
    public function index(Request $request)
    {
        $query = FaqItem::query()
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
                $q->whereRaw("question->>'en' ILIKE ?", ["%{$search}%"])
                  ->orWhereRaw("answer->>'en' ILIKE ?", ["%{$search}%"]);
            });
        }

        $items = $query->paginate(20);

        return response()->json([
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    /**
     * Get a single FAQ item.
     */
    public function show(FaqItem $item)
    {
        $item->load(['category', 'creator', 'updater']);

        return response()->json([
            'data' => $item,
        ]);
    }

    /**
     * Create a new FAQ item.
     */
    public function store(FaqItemRequest $request)
    {
        $user = $request->user();

        // Generate slug if not provided
        $slug = $request->slug;
        if (empty($slug) && isset($request->question['en'])) {
            $slug = FaqItem::generateSlug($request->question['en']);
        }

        $item = FaqItem::create([
            'slug' => $slug,
            'question' => $request->question,
            'answer' => $request->answer,
            'category_id' => $request->category_id,
            'order' => $request->order ?? 0,
            'is_published' => $request->boolean('is_published', false),
            'created_by' => $user->id,
        ]);

        $item->load(['category', 'creator']);

        return response()->json([
            'data' => $item,
            'message' => __('messages.faq_item_created'),
        ], 201);
    }

    /**
     * Update a FAQ item.
     */
    public function update(FaqItemRequest $request, FaqItem $item)
    {
        $user = $request->user();

        // Generate slug if not provided and question changed
        $slug = $request->slug ?? $item->slug;
        if (empty($request->slug) && isset($request->question['en']) && $request->question['en'] !== ($item->question['en'] ?? '')) {
            $slug = FaqItem::generateSlug($request->question['en']);
        }

        $item->update([
            'slug' => $slug,
            'question' => $request->question,
            'answer' => $request->answer,
            'category_id' => $request->category_id,
            'order' => $request->order ?? $item->order,
            'is_published' => $request->boolean('is_published', $item->is_published),
            'updated_by' => $user->id,
        ]);

        $item->load(['category', 'creator', 'updater']);

        return response()->json([
            'data' => $item,
            'message' => __('messages.faq_item_updated'),
        ]);
    }

    /**
     * Delete a FAQ item.
     */
    public function destroy(FaqItem $item)
    {
        $item->delete();

        return response()->json([
            'message' => __('messages.faq_item_deleted'),
        ]);
    }
}

