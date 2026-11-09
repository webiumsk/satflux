<?php

namespace App\Services\Faq;

use App\Models\FaqCategory;
use App\Models\FaqItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FaqSynchronizer
{
    /** Preview public identifiers and actions only; never mutate rows or metadata. */
    public function preview(array $catalog): array
    {
        return $this->plan($catalog, false);
    }

    /** Lock, re-plan, and commit the entire conflict-free batch as unpublished drafts. */
    public function apply(array $catalog, int $actorId): array
    {
        return DB::transaction(function () use ($catalog, $actorId) {
            $actor = User::query()->lockForUpdate()->find($actorId);
            if (! $actor || ! $actor->isSupport()) {
                throw new RuntimeException('FAQ import requires an existing support or admin actor.');
            }
            $plan = $this->plan($catalog, true);
            if (in_array('conflict', array_column($plan, 'action'), true)) {
                throw new RuntimeException('FAQ conflicts detected. No records were changed; review the preview and reconcile content manually.');
            }
            $categories = [];
            foreach ($catalog['categories'] as $entry) {
                $model = FaqCategory::query()->where('content_sync_key', $entry['slug'])->first();
                $new = $model === null;
                $model ??= new FaqCategory;
                $attributes = $this->categoryAttributes($entry, $model);
                if ($new) {
                    $attributes['is_active'] = true;
                }
                $this->save($model, $attributes, $entry['slug']);
                $categories[$entry['slug']] = $model->id;
            }
            foreach ($catalog['items'] as $entry) {
                $model = FaqItem::query()->where('content_sync_key', $entry['slug'])->first();
                $new = $model === null;
                $model ??= new FaqItem;
                $attributes = $this->itemAttributes($entry, $model, $categories[$entry['category']]);
                if ($new) {
                    $attributes['is_published'] = false;
                    $attributes['created_by'] = $actorId;
                } elseif ($this->fingerprint($model) !== $this->fingerprint($model->replicate()->fill($attributes))) {
                    $attributes['updated_by'] = $actorId;
                }
                $this->save($model, $attributes, $entry['slug']);
            }

            return $plan;
        });
    }

    /** Check all existing ownership and baselines before considering an individual write. */
    private function plan(array $catalog, bool $lock): array
    {
        $plan = [];
        $categoryIds = [];
        foreach ($catalog['categories'] as $entry) {
            $model = $this->existing(FaqCategory::class, $entry['slug'], $lock);
            $categoryIds[$entry['slug']] = $model === null ? 'new:'.$entry['slug'] : (string) $model->getKey();
            $plan[] = $this->action('category', $entry, $model, $this->categoryAttributes($entry, $model));
        }
        foreach ($catalog['items'] as $entry) {
            $model = $this->existing(FaqItem::class, $entry['slug'], $lock);
            $plan[] = $this->action('item', $entry, $model, $this->itemAttributes($entry, $model, $categoryIds[$entry['category']]));
        }

        return $plan;
    }

    /** A managed row may have been renamed; its immutable import key still finds it. */
    private function existing(string $class, string $slug, bool $lock): ?Model
    {
        $query = $class::query()->where(function ($query) use ($slug) {
            $query->where('content_sync_key', $slug)->orWhere('slug', $slug);
        })->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $models = $query->get();
        if ($models->count() > 1) {
            throw new RuntimeException('FAQ identifier collision: '.$slug.'. No records were changed.');
        }

        return $models->first();
    }

    /** Classify a catalog record against its immutable ownership key and last imported content hash. */
    private function action(string $type, array $entry, ?Model $model, array $attributes): array
    {
        $action = 'create';
        if ($model) {
            $current = $this->fingerprint($model);
            if ($model->getAttribute('content_sync_key') !== $entry['slug'] || ! is_string($model->getAttribute('content_sync_hash'))
                || ! hash_equals($model->getAttribute('content_sync_hash'), $current)) {
                $action = 'conflict';
            } else {
                $desired = $model->replicate()->fill($attributes);
                $action = hash_equals($current, $this->fingerprint($desired)) ? 'unchanged' : 'update';
            }
        }

        return ['type' => $type, 'slug' => $entry['slug'], 'action' => $action];
    }

    /** Merge reviewed category fields while preserving languages outside the managed locale set. */
    private function categoryAttributes(array $entry, ?Model $model): array
    {
        return [
            'slug' => $entry['slug'],
            'name' => array_replace($model?->getAttribute('name') ?? [], $entry['name']),
            'description' => array_replace($model?->getAttribute('description') ?? [], $entry['description']),
            'order' => $entry['order'],
        ];
    }

    /** Merge reviewed FAQ text and category membership without touching publication or counters. */
    private function itemAttributes(array $entry, ?Model $model, string $categoryId): array
    {
        return [
            'slug' => $entry['slug'],
            'question' => array_replace($model?->getAttribute('question') ?? [], $entry['question']),
            'answer' => array_replace($model?->getAttribute('answer') ?? [], $entry['answer']),
            'category_id' => $categoryId,
            'order' => $entry['order'],
        ];
    }

    /** Hash only imported fields; publication, counters and other languages remain locally owned. */
    private function fingerprint(Model $model): string
    {
        $fields = $model instanceof FaqCategory ? ['slug', 'order', 'name', 'description'] : ['slug', 'order', 'category_id', 'question', 'answer'];
        $attributes = [];
        foreach ($fields as $field) {
            $value = $model->getAttribute($field);
            if (in_array($field, ['question', 'answer', 'name', 'description'], true)) {
                $value = array_intersect_key(is_array($value) ? $value : [], array_flip(FaqCatalog::LOCALES));
                ksort($value);
            }
            $attributes[$field] = $value;
        }

        return hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Persist only changed managed content and its baseline hash without publishing or deleting rows. */
    private function save(Model $model, array $attributes, string $key): void
    {
        $model->fill($attributes);
        $model->setAttribute('content_sync_key', $key);
        $model->setAttribute('content_sync_hash', $this->fingerprint($model));
        if (! $model->exists || $model->isDirty()) {
            $model->save();
        }
    }
}
