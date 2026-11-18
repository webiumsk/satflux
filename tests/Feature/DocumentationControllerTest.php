<?php

namespace Tests\Feature;

use App\Services\Documentation\UserDocsRepository;
use Tests\TestCase;

class DocumentationControllerTest extends TestCase
{
    private string $docsDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->docsDir = sys_get_temp_dir().'/satflux-docs-'.uniqid();
        @mkdir($this->docsDir.'/en', 0777, true);
        @mkdir($this->docsDir.'/sk', 0777, true);

        file_put_contents($this->docsDir.'/categories.yaml', <<<'YAML'
        categories:
          - slug: getting-started
            order: 1
            name:
              en: Getting started
              sk: Začíname
          - slug: invoicing
            order: 2
            name:
              en: Invoicing
        YAML);

        file_put_contents($this->docsDir.'/en/intro.md', <<<'MD'
        ---
        title: Introduction
        category: getting-started
        order: 1
        meta_description: Start here.
        updated: 2026-08-27
        ---

        # Introduction

        Welcome to **Satflux**. It has a passkey feature.

        | A | B |
        |---|---|
        | 1 | 2 |

        <div data-example="raw">Literal HTML from a guide</div>

        [Unsupported link](file:///guide.md)
        MD);

        file_put_contents($this->docsDir.'/sk/intro.md', <<<'MD'
        ---
        title: Úvod
        category: getting-started
        order: 1
        ---

        # Úvod

        Vitajte v Satfluxe.
        MD);

        // Only in en - exercises the per-article locale fallback.
        file_put_contents($this->docsDir.'/en/billing.md', <<<'MD'
        ---
        title: Billing
        category: invoicing
        order: 1
        ---

        # Billing

        Invoices and payments.
        MD);

        file_put_contents($this->docsDir.'/sk/local-only.md', "# Translation without a canonical guide\n");
        file_put_contents($this->docsDir.'/internal.md', "# Internal test content\n");
        symlink($this->docsDir.'/internal.md', $this->docsDir.'/en/linked.md');

        $this->app->instance(UserDocsRepository::class, new UserDocsRepository($this->docsDir));
    }

    protected function tearDown(): void
    {
        foreach (['en/intro.md', 'sk/intro.md', 'en/billing.md', 'sk/local-only.md', 'en/linked.md', 'internal.md', 'categories.yaml'] as $file) {
            @unlink($this->docsDir.'/'.$file);
        }
        @rmdir($this->docsDir.'/en');
        @rmdir($this->docsDir.'/sk');
        @rmdir($this->docsDir);

        parent::tearDown();
    }

    public function test_index_lists_articles_and_categories_from_files(): void
    {
        $response = $this->getJson('/api/documentation?locale=en');

        $response->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
        $data = $response->json('data');
        $slugs = array_column($data, 'slug');

        $this->assertContains('intro', $slugs);
        $this->assertContains('billing', $slugs);
        // Category order (getting-started before invoicing) then article order.
        $this->assertSame('intro', $slugs[0]);
        $this->assertSame(['getting-started', 'invoicing'], array_column($response->json('categories'), 'slug'));
    }

    public function test_show_renders_markdown_to_html(): void
    {
        $response = $this->getJson('/api/documentation/intro?locale=en');

        $response->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
        $content = $response->json('data.content');
        $this->assertStringContainsString('<h1>Introduction</h1>', $content);
        $this->assertStringContainsString('<strong>Satflux</strong>', $content);
        // GitHub-flavored: tables render.
        $this->assertStringContainsString('<table>', $content);
        $this->assertSame('Introduction', $response->json('data.title'));
        $this->assertSame('getting-started', $response->json('data.category.id'));
        // Front-matter `updated` (a bare date YAML parses to a Unix timestamp)
        // is normalized back to an ISO Y-m-d string, not a raw number.
        $this->assertSame('2026-08-27', $response->json('data.updated_at'));
    }

    public function test_localized_content_is_served_when_present(): void
    {
        $response = $this->getJson('/api/documentation/intro?locale=sk');

        $response->assertOk();
        $this->assertSame('Úvod', $response->json('data.title'));
        // Category name is localized too.
        $this->assertSame('Začíname', $response->json('data.category.name'));
    }

    public function test_missing_translation_falls_back_to_english(): void
    {
        // billing.md only exists in en.
        $response = $this->getJson('/api/documentation/billing?locale=sk');

        $response->assertOk();
        $this->assertSame('Billing', $response->json('data.title'));
    }

    public function test_unsupported_cz_locale_does_not_error_and_cs_is_supported(): void
    {
        // cz was a legacy typo for cs - it must not be a supported locale.
        $this->assertNotContains('cz', UserDocsRepository::SUPPORTED_LOCALES);
        $this->assertContains('cs', UserDocsRepository::SUPPORTED_LOCALES);

        $this->getJson('/api/documentation/intro?locale=cz')->assertOk();
        $this->getJson('/api/documentation/intro?locale=cs')->assertOk();
    }

    public function test_search_filters_by_title_and_body(): void
    {
        $response = $this->getJson('/api/documentation?locale=en&search=passkey');

        $response->assertOk();
        $slugs = array_column($response->json('data'), 'slug');
        $this->assertSame(['intro'], $slugs);
    }

    public function test_category_filter(): void
    {
        $response = $this->getJson('/api/documentation?locale=en&category_id=invoicing');

        $response->assertOk();
        $this->assertSame(['billing'], array_column($response->json('data'), 'slug'));
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->getJson('/api/documentation/does-not-exist')->assertNotFound();
    }

    public function test_initial_directory_has_public_article_links_and_specific_metadata(): void
    {
        $this->withoutVite();
        config(['app.name' => 'Satflux', 'app.url' => 'https://panel.test']);

        $this->get('/documentation?utm_source=test')
            ->assertOk()
            ->assertHeader('Content-Language', 'en')
            ->assertSee('<title>Documentation - Satflux</title>', false)
            ->assertSee('<link rel="canonical" href="https://panel.test/documentation">', false)
            ->assertSee('data-spa="public"', false)
            ->assertSee('id="documentation-content"', false)
            ->assertSee('<h2 class="mb-3 text-2xl font-semibold">Getting started</h2>', false)
            ->assertSee('href="/documentation/intro"', false)
            ->assertSee('href="/documentation/billing"', false)
            ->assertDontSee('/documentation/linked')
            ->assertDontSee('/documentation/local-only');
    }

    public function test_initial_article_includes_safely_rendered_content_and_alternate_metadata(): void
    {
        $this->withoutVite();
        config(['app.name' => 'Satflux', 'app.url' => 'https://panel.test']);

        $response = $this->get('https://alias.test/documentation/intro?locale=sk', ['Accept-Language' => 'sk-SK']);

        $response->assertOk()
            ->assertHeader('Content-Language', 'en')
            ->assertSee('<html lang="en"', false)
            ->assertSee('<title>Introduction - Satflux</title>', false)
            ->assertSee('<meta name="description" content="Start here.">', false)
            ->assertSee('<link rel="canonical" href="https://panel.test/documentation/intro">', false)
            ->assertSee('rel="alternate" type="text/markdown" href="https://panel.test/documentation/intro.md"', false)
            ->assertSee('<article ', false)
            ->assertSee('<h1>Introduction</h1>', false)
            ->assertSee('<strong>Satflux</strong>', false)
            ->assertSee('<table>', false)
            ->assertSee('&lt;div', false)
            ->assertDontSee('<div data-example=', false)
            ->assertDontSee('href="file:', false);

        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-cache'));
    }

    public function test_markdown_has_actual_language_without_yaml_and_is_linked_only_for_published_guides(): void
    {
        $this->get('/documentation/intro.md?locale=sk', ['Accept-Language' => 'de'])
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
            ->assertHeader('Content-Language', 'sk')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('# Úvod', false)
            ->assertDontSee('category:')
            ->assertDontSee('title:')
            ->assertDontSee('<!DOCTYPE');

        $this->get('/documentation/billing.md?locale=es')
            ->assertOk()
            ->assertHeader('Content-Language', 'en')
            ->assertSee('# Billing', false);

        $this->get('/documentation/intro.md?locale[]=sk')
            ->assertOk()
            ->assertHeader('Content-Language', 'en')
            ->assertSee('**Satflux**', false)
            ->assertDontSee('meta_description:');

        $this->get('/documentation/local-only.md?locale=sk')->assertNotFound();
        $this->get('/documentation/linked.md')->assertNotFound()->assertDontSee('Internal test content');
    }

    public function test_missing_and_malformed_documentation_urls_have_http_404(): void
    {
        $this->withoutVite();

        $this->get('/documentation/no-such-guide')
            ->assertNotFound()
            ->assertSee('Article not found')
            ->assertSee('data-spa="public"', false)
            ->assertDontSee('type="text/markdown"', false);

        $this->get('/documentation/intro.php')->assertNotFound();
        $this->get('/documentation/no-such-guide.md')->assertNotFound();
        $this->get('/documentation/en/intro.md')->assertNotFound();
    }

    public function test_inertia_documentation_visit_keeps_full_page_handoff(): void
    {
        config(['app.url' => 'https://panel.test']);

        $this->get('/documentation/intro?source=app', ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://panel.test/documentation/intro?source=app');
    }

    public function test_llms_is_a_compact_configured_index_of_public_markdown_guides(): void
    {
        $this->app->instance(UserDocsRepository::class, new UserDocsRepository);
        config(['app.url' => 'https://panel.test/']);

        $response = $this->get('https://alias.test/llms.txt');
        $response->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertHeader('Content-Language', 'en')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('- [Documentation directory](https://panel.test/documentation)', false)
            ->assertSee('- [What is Satflux](https://panel.test/documentation/what-is-satflux.md)', false)
            ->assertDontSee('alias.test')
            ->assertDontSee('/api/')
            ->assertDontSee('<!DOCTYPE');

        $body = (string) $response->getContent();
        $this->assertStringStartsWith("# Satflux\n\n> ", $body);
        $this->assertLessThan(3000, strlen($body));

        preg_match_all('~^- \[.+\]\(https://panel\.test(/documentation/[a-z0-9-]+\.md)\)$~m', $body, $guides);
        $this->assertGreaterThanOrEqual(6, count($guides[1]));
        foreach ($guides[1] as $guide) {
            $this->get($guide)->assertOk()->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
        }
    }

    public function test_robots_allows_only_named_search_crawlers_to_fetch_public_docs_json(): void
    {
        config(['app.url' => 'https://panel.test/']);

        $response = $this->get('/robots.txt')->assertOk();
        $groups = explode("\n\n", trim((string) $response->getContent()));
        $this->assertCount(3, $groups);

        $defaultRules = explode("\n", $groups[0]);
        $this->assertSame('User-agent: *', array_shift($defaultRules));
        $this->assertContains('Disallow: /api/', $defaultRules);
        $this->assertContains('Disallow: /admin', $defaultRules);
        $this->assertNotContains('Allow: /api/documentation/', $defaultRules);

        $searchRules = explode("\n", $groups[1]);
        $this->assertSame(['User-agent: Googlebot', 'User-agent: OAI-SearchBot'], array_splice($searchRules, 0, 2));
        $this->assertSame($defaultRules, array_splice($searchRules, 0, count($defaultRules)));
        $this->assertSame([
            'Allow: /api/documentation$',
            'Allow: /api/documentation?',
            'Allow: /api/documentation/',
        ], $searchRules);
        $this->assertSame('Sitemap: https://panel.test/sitemap.xml', $groups[2]);
    }
}
