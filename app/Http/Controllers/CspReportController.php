<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives browser CSP violation reports (report-uri directive).
 *
 * Logs only the technical violation fields - never request bodies that could
 * carry user data. Rate-limited at the route; report bodies are size-capped.
 */
class CspReportController extends Controller
{
    private const MAX_BODY_BYTES = 16384;

    public function store(Request $request): Response
    {
        $raw = (string) $request->getContent();
        if ($raw === '' || strlen($raw) > self::MAX_BODY_BYTES) {
            return response()->noContent();
        }

        $decoded = json_decode($raw, true);
        $report = is_array($decoded) ? ($decoded['csp-report'] ?? $decoded) : null;
        if (! is_array($report)) {
            return response()->noContent();
        }

        Log::warning('CSP violation reported', [
            'document_uri' => $this->cleanUriField($report, 'document-uri'),
            'violated_directive' => $this->cleanField($report, 'violated-directive'),
            'effective_directive' => $this->cleanField($report, 'effective-directive'),
            'blocked_uri' => $this->cleanUriField($report, 'blocked-uri'),
            'source_file' => $this->cleanUriField($report, 'source-file'),
            'line_number' => $report['line-number'] ?? null,
            'disposition' => $this->cleanField($report, 'disposition'),
        ]);

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function cleanField(array $report, string $key): ?string
    {
        $value = $report[$key] ?? null;

        return is_string($value) ? substr($value, 0, 512) : null;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function cleanUriField(array $report, string $key): ?string
    {
        $value = $this->cleanField($report, $key);
        if ($value === null) {
            return null;
        }

        if (str_starts_with(strtolower($value), 'data:')) {
            return 'data:';
        }

        $withoutFragment = explode('#', $value, 2)[0];
        $withoutQuery = explode('?', $withoutFragment, 2)[0];
        $parts = parse_url($withoutQuery);
        if (! is_array($parts)) {
            return substr($withoutQuery, 0, 512);
        }

        $safe = '';
        if (isset($parts['scheme'])) {
            $safe .= $parts['scheme'].':';
        }
        if (isset($parts['host'])) {
            $safe .= '//'.$parts['host'];
            if (isset($parts['port'])) {
                $safe .= ':'.$parts['port'];
            }
        }
        $safe .= $parts['path'] ?? '';

        return substr($safe !== '' ? $safe : $withoutQuery, 0, 512);
    }
}
