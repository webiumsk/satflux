<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RegWatchChangeStatus;
use App\Http\Controllers\Controller;
use App\Models\RegWatchChange;
use App\Models\RegWatchSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin review of RegWatch detections (docs/LEGAL.md): list changes, view
 * the diff and move a change through new -> reviewed -> applied/dismissed.
 * This controller only manages the changelog - rule content stays a manual
 * edit after verifying the official source.
 */
class RegWatchController extends Controller
{
    public function changes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(RegWatchChangeStatus::class)],
            'source_id' => ['nullable', 'uuid'],
        ]);

        $query = RegWatchChange::query()
            ->with(['source.jurisdiction', 'reviewer:id,email'])
            ->orderByDesc('detected_at');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['source_id'])) {
            $query->where('source_id', $validated['source_id']);
        }

        $changes = $query->paginate(20);

        return response()->json([
            'data' => collect($changes->items())->map(fn (RegWatchChange $change) => $this->formatChange($change))->all(),
            'meta' => [
                'current_page' => $changes->currentPage(),
                'last_page' => $changes->lastPage(),
                'per_page' => $changes->perPage(),
                'total' => $changes->total(),
            ],
        ]);
    }

    public function showChange(RegWatchChange $change): JsonResponse
    {
        $change->load(['source.jurisdiction', 'reviewer:id,email', 'rule:id,slug,title']);

        return response()->json([
            'data' => $this->formatChange($change, withDiff: true),
        ]);
    }

    public function updateChangeStatus(Request $request, RegWatchChange $change): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(RegWatchChangeStatus::class)],
        ]);

        $target = RegWatchChangeStatus::from($validated['status']);

        if (! in_array($target, $change->status->allowedTransitions(), true)) {
            return response()->json([
                'message' => __('Invalid status transition from :from to :to.', [
                    'from' => $change->status->value,
                    'to' => $target->value,
                ]),
            ], 422);
        }

        $change->forceFill([
            'status' => $target,
            // First move out of 'new' stamps who reviewed and when.
            'reviewed_at' => $change->reviewed_at ?? now(),
            'reviewed_by' => $change->reviewed_by ?? $request->user()->id,
        ])->save();

        $change->load(['source.jurisdiction', 'reviewer:id,email']);

        return response()->json([
            'data' => $this->formatChange($change),
            'message' => __('Status updated.'),
        ]);
    }

    public function sources(): JsonResponse
    {
        $sources = RegWatchSource::query()
            ->with('jurisdiction:id,code,name')
            ->withCount(['changes as new_changes_count' => fn ($q) => $q->where('status', RegWatchChangeStatus::New)])
            ->orderBy('slug')
            ->get();

        return response()->json([
            'data' => $sources->map(fn (RegWatchSource $source) => [
                'id' => $source->id,
                'slug' => $source->slug,
                'name' => $source->name,
                'url' => $source->url,
                'type' => $source->type->value,
                'active' => $source->active,
                'jurisdiction_code' => $source->jurisdiction?->code,
                'last_checked_at' => $source->last_checked_at?->toIso8601String(),
                'new_changes_count' => $source->new_changes_count,
            ])->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatChange(RegWatchChange $change, bool $withDiff = false): array
    {
        $data = [
            'id' => $change->id,
            'status' => $change->status->value,
            'summary' => $change->summary,
            'classification' => $change->classification_json,
            'detected_at' => $change->detected_at->toIso8601String(),
            'reviewed_at' => $change->reviewed_at?->toIso8601String(),
            'reviewed_by_email' => $change->reviewer?->email,
            'allowed_transitions' => array_map(
                fn (RegWatchChangeStatus $status) => $status->value,
                $change->status->allowedTransitions(),
            ),
            'source' => $change->source ? [
                'id' => $change->source->id,
                'slug' => $change->source->slug,
                'name' => $change->source->name,
                'url' => $change->source->url,
                'jurisdiction_code' => $change->source->jurisdiction?->code,
            ] : null,
        ];

        if ($withDiff) {
            $data['diff'] = $change->diff;
            $data['rule'] = $change->rule ? [
                'id' => $change->rule->id,
                'slug' => $change->rule->slug,
                'title' => $change->rule->title,
            ] : null;
        }

        return $data;
    }
}
