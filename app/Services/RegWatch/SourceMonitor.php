<?php

namespace App\Services\RegWatch;

use App\Enums\RegWatchChangeStatus;
use App\Models\RegWatchChange;
use App\Models\RegWatchSource;
use App\Notifications\RegWatchChangeDetected;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * RegWatch monitoring core (docs/LEGAL.md): fetch a source, normalize it,
 * diff against the stored snapshot and record a detection in
 * regwatch_changes (status 'new') for human review. This pipeline NEVER
 * writes to regwatch_rules - rules are edited only by a human after review.
 */
class SourceMonitor
{
    public function __construct(
        private readonly DiffClassifier $classifier,
    ) {}

    /**
     * Check one source. Returns the created change row, or null when
     * nothing changed (or the fetch failed / first-run baseline was stored).
     */
    public function check(RegWatchSource $source): ?RegWatchChange
    {
        $body = $this->fetch($source);
        if ($body === null) {
            return null;
        }

        $normalized = $this->normalize($body);
        $hash = hash('sha256', $normalized);

        if ($hash === $source->last_snapshot_hash) {
            $source->forceFill(['last_checked_at' => now()])->save();

            return null;
        }

        $previous = $this->readSnapshot($source);
        $this->writeSnapshot($source, $normalized);
        $hadBaseline = $source->last_snapshot_hash !== null && $previous !== null;
        $source->forceFill([
            'last_checked_at' => now(),
            'last_snapshot_hash' => $hash,
        ])->save();

        if (! $hadBaseline) {
            // First run: store the baseline silently - there is nothing to
            // diff against yet, and a "whole page added" change is noise.
            Log::info('[regwatch] baseline snapshot stored', ['source' => $source->slug]);

            return null;
        }

        $maxChars = (int) config('regwatch.max_diff_chars', 20000);
        $diff = SnapshotDiff::diff($previous, $normalized, $maxChars);
        if ($diff === '') {
            // Hash differs but no line-level change (e.g. whitespace only).
            return null;
        }

        $classification = $this->classifier->classify($source, $diff);

        $change = RegWatchChange::create([
            'source_id' => $source->id,
            'status' => RegWatchChangeStatus::New,
            'summary' => $classification !== null && $classification['summary'] !== ''
                ? $classification['summary']
                : null,
            'diff' => $diff,
            'classification_json' => $classification,
            'detected_at' => now(),
        ]);

        $this->notify($source, $change);

        return $change;
    }

    private function fetch(RegWatchSource $source): ?string
    {
        try {
            $response = Http::withHeaders(['User-Agent' => 'satflux-regwatch/1.0'])
                ->timeout((int) config('regwatch.http_timeout', 30))
                ->get($source->url);

            if (! $response->successful()) {
                Log::warning('[regwatch] source fetch failed', [
                    'source' => $source->slug,
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $response->body();
        } catch (\Throwable $e) {
            Log::warning('[regwatch] source fetch failed', [
                'source' => $source->slug,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Reduce an HTML page to comparable text: drop script/style blocks
     * (deploy-hashed bundles would flag a change on every release), strip
     * tags and collapse whitespace line by line.
     */
    private function normalize(string $html): string
    {
        $text = preg_replace('/<(script|style|noscript)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;
        $text = preg_replace('/<!--.*?-->/s', ' ', $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $lines = preg_split('/\R/u', $text) ?: [];
        $lines = array_filter(array_map(
            fn (string $line) => trim(preg_replace('/\s+/u', ' ', $line) ?? $line),
            $lines,
        ), fn (string $line) => $line !== '');

        return implode("\n", $lines);
    }

    private function snapshotPath(RegWatchSource $source): string
    {
        return trim((string) config('regwatch.snapshot_dir'), '/')."/{$source->slug}.txt";
    }

    private function readSnapshot(RegWatchSource $source): ?string
    {
        $disk = Storage::disk((string) config('regwatch.snapshot_disk', 'local'));
        $path = $this->snapshotPath($source);

        return $disk->exists($path) ? $disk->get($path) : null;
    }

    private function writeSnapshot(RegWatchSource $source, string $normalized): void
    {
        Storage::disk((string) config('regwatch.snapshot_disk', 'local'))
            ->put($this->snapshotPath($source), $normalized);
    }

    private function notify(RegWatchSource $source, RegWatchChange $change): void
    {
        $email = (string) config('regwatch.notify_email');
        if ($email === '') {
            Log::info('[regwatch] change detected (no notify_email configured)', [
                'source' => $source->slug,
                'change_id' => $change->id,
            ]);

            return;
        }

        try {
            Notification::route('mail', $email)
                ->notify(new RegWatchChangeDetected($source, $change));
        } catch (\Throwable $e) {
            Log::warning('[regwatch] notification failed', [
                'source' => $source->slug,
                'change_id' => $change->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
