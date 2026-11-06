<?php

namespace Tests\Feature;

use App\Support\HomepageDemoUrl;
use App\Support\HomepageVariant;
use App\Support\LandingCopy;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HomepageVariantTest extends TestCase
{
    /** Supply valid and malformed configuration values with their expected fallback. */
    public static function variants(): array
    {
        return [
            'legacy' => ['legacy', 'legacy'],
            'showcase' => ['showcase', 'showcase'],
            'missing' => [null, 'legacy'],
            'empty' => ['', 'legacy'],
            'unknown' => ['preview', 'legacy'],
            'wrong case' => ['SHOWCASE', 'legacy'],
            'boolean' => [true, 'legacy'],
            'array' => [['showcase'], 'legacy'],
            'markup' => ['" onmouseover="alert(1)', 'legacy'],
        ];
    }

    #[DataProvider('variants')]
    /** Verify both SPA bootstrap paths and the selected first-paint shell use one safe variant. */
    public function test_both_documents_use_the_resolved_variant_and_matching_public_shell(mixed $configured, string $expected): void
    {
        $this->withoutVite();
        config(['marketing.homepage_variant' => $configured]);

        $this->assertSame($expected, HomepageVariant::resolve());
        $response = $this->get('/');
        $response->assertOk()->assertSee('data-homepage-variant="'.$expected.'"', false);
        $response->assertSee('id="landing-shell"', false);
        $response->assertSee($expected === 'showcase' ? 'id="hero-title"' : 'id="landing-hero-heading"', false);
        $response->assertDontSee($expected === 'showcase' ? 'id="landing-hero-heading"' : 'id="hero-title"', false);

        foreach ([[], ['page' => ['component' => 'stores/apps/Index', 'props' => []]]] as $data) {
            $document = view('app', $data)->render();
            $this->assertStringContainsString('data-homepage-variant="'.$expected.'"', $document);
            $this->assertStringNotContainsString('id="landing-shell"', $document);
        }
    }

    /** Prevent public query parameters from changing the configured homepage. */
    public function test_public_query_parameters_cannot_override_the_server_variant(): void
    {
        $this->withoutVite();
        config(['marketing.homepage_variant' => 'legacy']);

        $this->get('/?homepage_variant=showcase&variant=showcase')
            ->assertOk()
            ->assertSee('data-homepage-variant="legacy"', false)
            ->assertSee('id="landing-hero-heading"', false);
    }

    /** Keep internal public-page navigation aware of the variant without rendering a homepage shell. */
    public function test_non_home_public_pages_receive_bootstrap_without_a_landing_shell(): void
    {
        $this->withoutVite();
        config(['marketing.homepage_variant' => 'showcase']);

        $this->get('/pricing')
            ->assertOk()
            ->assertSee('data-homepage-variant="showcase"', false)
            ->assertDontSee('id="landing-shell"', false);
    }

    /** Preserve the full-document handoff when an Inertia visit reaches the public homepage. */
    public function test_inertia_home_visits_keep_the_existing_full_document_boundary(): void
    {
        config(['marketing.homepage_variant' => 'showcase', 'app.url' => 'https://panel.test']);

        $this->get('/', ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://panel.test/');
    }

    /** Escape a valid public demo URL consistently in both document templates. */
    public function test_public_demo_url_is_escaped_in_both_documents(): void
    {
        $this->withoutVite();
        config(['marketing.demo_pos_url' => 'https://demo.example/pos?first=1&second=2']);

        foreach (['public', 'app'] as $view) {
            $document = view($view)->render();
            $this->assertStringContainsString('data-homepage-demo-pos-url="https://demo.example/pos?first=1&amp;second=2"', $document);
            $this->assertStringNotContainsString('data-homepage-demo-pos-url="https://demo.example/pos?first=1&second=2"', $document);
        }
    }

    /** Supply malformed, unsafe and credential-bearing demo URL configurations. */
    public static function unsafeDemoUrls(): array
    {
        return [
            'missing' => [null],
            'array' => [['https://demo.example/pos']],
            'credentials' => ['https://operator:synthetic-secret@demo.example/pos'],
            'username' => ['https://operator@demo.example/pos'],
            'encoded username' => ['https://operator%3Asecret@demo.example/pos'],
            'http' => ['http://demo.example/pos'],
            'script' => ['javascript:alert(1)'],
            'data' => ['data:text/html,demo'],
            'relative' => ['/pos'],
            'protocol relative' => ['//demo.example/pos'],
            'markup' => ['https://demo.example/pos?title=" onload="alert(1)'],
        ];
    }

    #[DataProvider('unsafeDemoUrls')]
    /** Ensure rejected demo URL values cannot disclose credentials through either HTML bootstrap. */
    public function test_unsafe_demo_configuration_is_never_published_in_either_document(mixed $url): void
    {
        $this->withoutVite();
        config(['marketing.demo_pos_url' => $url]);
        $this->assertSame('', HomepageDemoUrl::resolve());

        foreach (['public', 'app'] as $view) {
            $document = view($view)->render();
            $this->assertStringContainsString('data-homepage-demo-pos-url=""', $document);
            if (is_string($url)) {
                $this->assertStringNotContainsString(e($url), $document);
            }
        }
    }

    /** Verify the first-paint hero resolves the same authored copy in all five supported locales. */
    public function test_showcase_shell_uses_the_shared_localized_hero_copy(): void
    {
        foreach (['en', 'sk', 'es', 'de', 'cs'] as $locale) {
            app()->setLocale($locale);
            $document = view('partials.landing-showcase-shell')->render();
            foreach (['eyebrow', 'line1', 'line2', 'line3', 'intro', 'start', 'demo'] as $key) {
                $copy = LandingCopy::get('showcase.hero.'.$key);
                $this->assertNotSame('showcase.hero.'.$key, $copy);
                $this->assertStringContainsString(e($copy), $document);
            }
        }
    }
}
