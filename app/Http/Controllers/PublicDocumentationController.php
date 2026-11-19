<?php

namespace App\Http\Controllers;

use App\Services\Documentation\UserDocsRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicDocumentationController extends Controller
{
    private const FEATURED_GUIDES = [
        'what-is-satflux',
        'plans-and-limits',
        'creating-your-first-store',
        'wallet-connection-overview',
        'point-of-sale',
        'getting-started-with-invoicing',
        'import-expenses',
        'what-we-store-and-how-its-protected',
    ];

    public function __construct(private UserDocsRepository $docs) {}

    public function html(Request $request, ?string $slug = null): Response
    {
        $directoryUrl = $this->siteUrl().'/documentation';

        // Preserve the SPA boundary for visits from an Inertia page.
        if ($request->header('X-Inertia')) {
            return response('', 409, [
                'X-Inertia-Location' => $this->siteUrl().'/'.ltrim($request->getRequestUri(), '/'),
                'Cache-Control' => 'private, no-cache',
            ]);
        }

        $locale = UserDocsRepository::FALLBACK_LOCALE;
        $article = $slug !== null ? $this->docs->article($slug, $locale) : null;
        $notFound = $slug !== null && $article === null;
        $articleUrl = $article !== null ? $directoryUrl.'/'.$article['slug'] : null;
        $articles = $slug === null ? $this->docs->articles($locale) : [];

        return response()->view('public', [
            'documentLanguage' => $locale,
            'documentation' => [
                'article' => $article,
                'articles' => $articles,
                'notFound' => $notFound,
            ],
            'seoTitle' => $article['title'] ?? ($notFound ? 'Article not found' : 'Documentation'),
            'seoDescription' => $article['meta_description'] ?? $article['title'] ?? 'Satflux guides for Bitcoin payments, invoicing, expenses, wallet setup, and integrations.',
            'canonicalUrl' => $articleUrl ?? ($notFound ? null : $directoryUrl),
            'markdownUrl' => $articleUrl !== null ? $articleUrl.'.md' : null,
            'noIndex' => $notFound,
        ], $notFound ? 404 : 200, [
            'Content-Language' => $locale,
            'Cache-Control' => 'private, no-cache',
        ]);
    }

    public function markdown(Request $request, string $slug): Response
    {
        $locale = $request->query('locale');
        $article = $this->docs->markdown(
            $slug,
            $this->docs->normalizeLocale(is_string($locale) ? $locale : null),
        );
        abort_if($article === null, 404);

        return response($article['content'], 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Language' => $article['locale'],
            'Cache-Control' => 'private, no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function llms(): Response
    {
        $siteUrl = $this->siteUrl();
        $directoryUrl = $siteUrl.'/documentation';
        $lines = [
            '# Satflux',
            '',
            '> Satflux is a non-custodial BTCPay Server control panel for merchants accepting Bitcoin and Lightning.',
            '',
            '## Product and help',
            '',
            "- [Documentation directory]({$directoryUrl})",
            "- [Pricing]({$siteUrl}/pricing)",
            "- [FAQ]({$siteUrl}/faq)",
            '',
            '## Guides in Markdown',
            '',
        ];
        foreach (self::FEATURED_GUIDES as $slug) {
            $article = $this->docs->article($slug, UserDocsRepository::FALLBACK_LOCALE);
            if ($article !== null) {
                $title = str_replace(['\\', '[', ']', "\r", "\n"], ['\\\\', '\\[', '\\]', ' ', ' '], $article['title']);
                $lines[] = "- [{$title}]({$directoryUrl}/{$slug}.md)";
            }
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Language' => UserDocsRepository::FALLBACK_LOCALE,
            'Cache-Control' => 'private, no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function siteUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }
}
