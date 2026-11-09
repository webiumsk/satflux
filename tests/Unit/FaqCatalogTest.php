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

    /** Remove synthetic source files and links after each isolated catalog validation scenario. */
    protected function tearDown(): void
    {
        foreach (['docs/faq/catalog.json', 'docs/faq/sources.lock.json', 'docs/user/en/start.md', 'outside.md'] as $path) {
            if (is_file($this->root.'/'.$path)) {
                unlink($this->root.'/'.$path);
            }
        }
        foreach (array_diff(FaqCatalog::LOCALES, ['en']) as $locale) {
            if (is_file($this->root.'/docs/user/'.$locale.'/start.md') || is_link($this->root.'/docs/user/'.$locale.'/start.md')) {
                unlink($this->root.'/docs/user/'.$locale.'/start.md');
                rmdir($this->root.'/docs/user/'.$locale);
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

    /** Reject unsupported URL schemes, malformed links and raw HTML before importing content. */
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

    /** Reject source paths outside the permitted documentation grammar and repeated source declarations. */
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

    /** Existing translations belong to each dependent FAQ, even when another item already tracks them. */
    public function test_existing_translated_source_cannot_be_omitted_from_one_dependent_item(): void
    {
        $this->addTranslatedSource('sk');
        $second = $this->catalog['items'][0];
        $second['slug'] = 'second';
        $second['sources'] = ['docs/user/en/start.md'];
        $this->catalog['items'][] = $second;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('existing translated source: docs/user/sk/start.md');
        $this->load();
    }

    /** A locale file appearing on disk must be declared even if the old lock did not mention it. */
    public function test_newly_available_translation_must_be_declared_and_fingerprinted(): void
    {
        mkdir($this->root.'/docs/user/cs', 0700);
        file_put_contents($this->root.'/docs/user/cs/start.md', '# Český zdroj');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('existing translated source: docs/user/cs/start.md');
        $this->load();
    }

    /** A declared translated symlink outside docs/user must fail the same source-boundary checks. */
    public function test_translated_symlink_outside_documentation_root_is_rejected(): void
    {
        mkdir($this->root.'/docs/user/cs', 0700);
        file_put_contents($this->root.'/outside.md', '# Outside documentation root');
        symlink($this->root.'/outside.md', $this->root.'/docs/user/cs/start.md');
        $this->catalog['items'][0]['sources'][] = 'docs/user/cs/start.md';
        $lock = json_decode(file_get_contents($this->root.'/docs/faq/sources.lock.json'), true, 512, JSON_THROW_ON_ERROR);
        $lock['docs/user/cs/start.md'] = hash_file('sha256', $this->root.'/outside.md');
        file_put_contents($this->root.'/docs/faq/sources.lock.json', json_encode($lock, JSON_THROW_ON_ERROR));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing or changed FAQ source: docs/user/cs/start.md');
        $this->load();
    }

    /** Missing translated articles keep the established canonical-English fallback valid. */
    public function test_unavailable_translated_article_keeps_english_fallback_valid(): void
    {
        $this->assertFileDoesNotExist($this->root.'/docs/user/cs/start.md');
        $this->assertSame($this->catalog, $this->load());
    }

    /** Declaring and locking an available translation satisfies coverage without changing its bytes. */
    public function test_available_translated_source_with_reviewed_fingerprint_is_accepted(): void
    {
        $this->addTranslatedSource('de');
        $this->assertSame($this->catalog, $this->load());
    }

    /** Category and item order boundaries match PostgreSQL signed integer storage exactly. */
    public function test_zero_and_signed_integer_maximum_orders_are_accepted_for_both_record_types(): void
    {
        foreach ([0, 2147483647] as $order) {
            $this->catalog['categories'][0]['order'] = $order;
            $this->catalog['items'][0]['order'] = $order;
            $this->assertSame($this->catalog, $this->load());
        }
    }

    /** Neither category nor item overflow may pass offline validation and later fail during import. */
    public function test_signed_integer_overflow_is_rejected_for_categories_and_items(): void
    {
        foreach (['categories', 'items'] as $type) {
            $this->catalog['categories'][0]['order'] = 1;
            $this->catalog['items'][0]['order'] = 1;
            $this->catalog[$type][0]['order'] = 2147483648;
            try {
                $this->load();
                $this->fail('PostgreSQL integer overflow must fail validation.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Invalid FAQ slug or ordering.', $exception->getMessage());
            }
        }
    }

    /** Preserve raw decimal/exponent JSON syntax while accepting only bounded whole decoded numbers. */
    public function test_raw_json_whole_number_order_literals_are_accepted_and_normalized(): void
    {
        $valid = [['0', 0], ['-0.0', 0], ['1', 1], ['1.0', 1], ['1e0', 1], ['1E+0', 1],
            ['2147483647.0', 2147483647], ['2.147483647e9', 2147483647]];
        foreach (['categories', 'items'] as $type) {
            foreach ($valid as [$literal, $expected]) {
                $content = $this->loadWithRawOrderLiteral($type, $literal);
                $this->assertSame($expected, $content[$type][0]['order'], $type.' raw order '.$literal);
            }
        }
    }

    /** Raw JSON strings, fractions, booleans, null, nonfinite values and overflowing numbers fail closed. */
    public function test_raw_json_invalid_order_literals_are_rejected_for_both_record_types(): void
    {
        $invalid = ['-1', '-1.0', '1.5', '1e-1', '2147483648', '2147483648.0', '1e309', '9007199254740993',
            '"1"', '"1.0"', 'true', 'null'];
        foreach (['categories', 'items'] as $type) {
            foreach ($invalid as $literal) {
                try {
                    $this->loadWithRawOrderLiteral($type, $literal);
                    $this->fail($type.' raw order '.$literal.' must be rejected.');
                } catch (RuntimeException $exception) {
                    $this->assertSame('Invalid FAQ slug or ordering.', $exception->getMessage());
                }
            }
        }
    }

    /** Write the requested numeric token directly so JSON encoding cannot canonicalize its lexical form. */
    private function loadWithRawOrderLiteral(string $type, string $literal): array
    {
        $content = $this->catalog;
        $content[$type][0]['order'] = '__FAQ_ORDER_LITERAL__';
        $json = str_replace('"__FAQ_ORDER_LITERAL__"', $literal, json_encode($content, JSON_THROW_ON_ERROR));
        file_put_contents($this->root.'/docs/faq/catalog.json', $json);

        return (new FaqCatalog)->load($this->root);
    }

    /** Create one synthetic translation and add its per-item declaration and reviewed lock entry. */
    private function addTranslatedSource(string $locale): void
    {
        mkdir($this->root.'/docs/user/'.$locale, 0700);
        $path = 'docs/user/'.$locale.'/start.md';
        file_put_contents($this->root.'/'.$path, '# Reviewed translated source');
        $this->catalog['items'][0]['sources'][] = $path;
        $lock = json_decode(file_get_contents($this->root.'/docs/faq/sources.lock.json'), true, 512, JSON_THROW_ON_ERROR);
        $lock[$path] = hash_file('sha256', $this->root.'/'.$path);
        file_put_contents($this->root.'/docs/faq/sources.lock.json', json_encode($lock, JSON_THROW_ON_ERROR));
    }

    private function load(): array
    {
        file_put_contents($this->root.'/docs/faq/catalog.json', json_encode($this->catalog, JSON_THROW_ON_ERROR));

        return (new FaqCatalog)->load($this->root);
    }
}
