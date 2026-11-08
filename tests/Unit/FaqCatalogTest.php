<?php

namespace Tests\Unit;

use App\Services\Faq\FaqCatalog;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FaqCatalogTest extends TestCase
{
    private string $root;

    private array $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/faq-catalog-'.bin2hex(random_bytes(8));
        mkdir($this->root.'/docs/faq', 0700, true);
        mkdir($this->root.'/docs/user/en', 0700, true);
        file_put_contents($this->root.'/docs/user/en/start.md', '# Reviewed source');
        $localized = array_fill_keys(FaqCatalog::LOCALES, 'Example');
        $this->catalog = ['version' => 1,
            'categories' => [['slug' => 'start', 'order' => 1, 'name' => $localized, 'description' => $localized]],
            'items' => [['slug' => 'first', 'order' => 1, 'category' => 'start', 'question' => $localized,
                'answer' => array_fill_keys(FaqCatalog::LOCALES, 'Read [the guide](/documentation/start).'),
                'sources' => ['docs/user/en/start.md']]],
        ];
        file_put_contents($this->root.'/docs/faq/sources.lock.json', json_encode([
            'docs/user/en/start.md' => hash_file('sha256', $this->root.'/docs/user/en/start.md'),
        ], JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        foreach (['docs/faq/catalog.json', 'docs/faq/sources.lock.json', 'docs/user/en/start.md'] as $path) {
            if (is_file($this->root.'/'.$path)) {
                unlink($this->root.'/'.$path);
            }
        }
        foreach (['docs/faq', 'docs/user/en', 'docs/user', 'docs', ''] as $path) {
            rmdir($this->root.($path === '' ? '' : '/'.$path));
        }
        parent::tearDown();
    }

    public function test_complete_reviewed_catalog_loads_without_an_application_or_database(): void
    {
        $this->assertSame($this->catalog, $this->load());
    }

    public function test_changed_source_fails_with_review_instruction(): void
    {
        file_put_contents($this->root.'/docs/user/en/start.md', '# Source changed');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Review its FAQ translations');
        $this->load();
    }

    public function test_missing_language_is_rejected(): void
    {
        unset($this->catalog['items'][0]['answer']['cs']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exactly EN/SK/ES/DE/CS');
        $this->load();
    }

    public function test_external_and_malformed_links_and_html_are_rejected(): void
    {
        foreach (['Read [guide](https://outside.test/guide)', 'Read [guide](javascript:alert(1))',
            'Read [guide](/documentation/start) and [broken](javascript:foo bar)',
            'Read [guide](/documentation/start) <img src=x>',
            'Read [guide](/documentation/start) and [reference][external]'] as $answer) {
            $this->catalog['items'][0]['answer']['en'] = $answer;
            try {
                $this->load();
                $this->fail('Unsafe answer must be rejected.');
            } catch (RuntimeException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function test_documentation_link_must_match_declared_source(): void
    {
        $this->catalog['items'][0]['answer']['en'] = 'Read [other](/documentation/not-declared).';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('declared English');
        $this->load();
    }

    public function test_path_traversal_and_duplicate_sources_are_rejected(): void
    {
        foreach ([['docs/user/en/../../secret.md'], ['docs/user/en/start.md', 'docs/user/en/start.md']] as $sources) {
            $this->catalog['items'][0]['sources'] = $sources;
            try {
                $this->load();
                $this->fail('Invalid sources must fail.');
            } catch (RuntimeException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function test_unused_source_lock_entries_fail_closed(): void
    {
        file_put_contents($this->root.'/docs/faq/sources.lock.json', json_encode([
            'docs/user/en/start.md' => hash_file('sha256', $this->root.'/docs/user/en/start.md'),
            'docs/user/en/unused.md' => str_repeat('a', 64),
        ], JSON_THROW_ON_ERROR));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exactly cover');
        $this->load();
    }

    private function load(): array
    {
        file_put_contents($this->root.'/docs/faq/catalog.json', json_encode($this->catalog, JSON_THROW_ON_ERROR));

        return (new FaqCatalog)->load($this->root);
    }
}
